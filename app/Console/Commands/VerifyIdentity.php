<?php

namespace App\Console\Commands;

use App\Models\Employee\Employee;
use App\Models\Person\Person;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfileDocument;
use App\Models\User\UserProfiles;
use App\Services\Identity\EmployeeSyncService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

/**
 * Parity check (roadmap_migrasi_bertahap_identity_hris.md bagian 7): membandingkan data lama
 * (users, user_profiles, user_job_positions, user_profile_documents) dengan salinannya
 * (persons*, employees, employee_careers, person_documents) untuk SEMUA user. HANYA MEMBACA -
 * tidak menulis apa pun. Sebuah fitur baru boleh dipindah ke tabel baru kalau dimensi yang
 * dibacanya nol selisih. Keluaran cuma jumlah & NIP (tanpa nilai data sensitif); exit code 1
 * kalau ada selisih.
 *
 * Selisih biasanya berarti: update/hapus massal yang tidak memicu observer, sinkron yang gagal
 * (cek log), atau dua sumber jabatan (profil vs riwayat) yang memang berbeda di data lama.
 * Perbaiki sinkron dengan identity:backfill & identity:backfill-employees.
 */
class VerifyIdentity extends Command
{
    protected $signature = 'identity:verify {--user= : Batasi ke satu NIP} {--limit=15 : Jumlah NIP contoh per dimensi}';

    protected $description = 'Bandingkan data lama dengan salinan persons/employees (hanya membaca)';

    /** @var array<string, array{checked: int, bad: array<int, string>}> */
    private array $stats = [];

    public function handle(): int
    {
        $skipped = EmployeeSyncService::skippedUserIds();

        User::withoutGlobalScopes()->withTrashed()
            ->when($this->option('user'), fn ($q, $nip) => $q->where('nip', $nip))
            ->chunkById(300, function ($users) use ($skipped) {
                $ids = $users->pluck('id');

                $maps = [
                    'profiles' => UserProfiles::whereIn('user_id', $ids)->get()->keyBy('user_id'),
                    'persons' => Person::with(['identity', 'addresses', 'documents'])->whereIn('user_id', $ids)->get()->keyBy('user_id'),
                    'employees' => Employee::with('careers')->whereIn('user_id', $ids)->get()->keyBy('user_id'),
                    'ledger' => UserJobPositions::whereIn('user_id', $ids)->get()->groupBy('user_id'),
                    'docs' => UserProfileDocument::whereIn('user_id', $ids)->get()->groupBy('user_id'),
                ];

                foreach ($users as $user) {
                    $this->verifyUser($user, $maps, in_array($user->id, $skipped, true));
                }
            });

        return $this->report();
    }

    private function verifyUser(User $user, array $maps, bool $skipEmployee): void
    {
        $profile = $maps['profiles'][$user->id] ?? null;
        $person = $maps['persons'][$user->id] ?? null;
        $employee = $maps['employees'][$user->id] ?? null;
        $ledger = $maps['ledger'][$user->id] ?? collect();
        $docs = $maps['docs'][$user->id] ?? collect();

        $this->check('persons: baris ada', $user, $person !== null);

        if ($person) {
            $this->check('persons: data pribadi', $user, $this->same($person, [
                'nama_lengkap' => $user->name,
                'nickname' => $profile?->nickname,
                'gender' => $profile?->gender,
                'marital_status' => $profile?->marital_status,
                'place_of_birth' => $profile?->place_of_birth,
                'date_of_birth' => $profile?->date_of_birth,
                'religion_id' => $profile?->religion_id,
                'phone' => $profile?->phone,
                'whatsapp' => $profile?->whatsapp,
                'email_pribadi' => $profile?->email_pribadi,
            ]));

            if ($profile) {
                $this->check('persons: identitas & rekening', $user, $person->identity !== null && $this->same($person->identity, $profile->only([
                    'no_nik', 'no_kk', 'npwp', 'no_bpjs_kes', 'no_bpjs_ket',
                    'bank_name', 'bank_account_number', 'bank_account_name',
                ])));

                $address = $person->addresses->firstWhere('type', 'utama');
                $this->check('persons: alamat', $user, $address !== null && $this->same($address, $profile->only([
                    'country', 'province', 'city', 'district', 'village', 'address', 'postal_code', 'rt', 'rw',
                ])));
            }

            $this->check(
                'persons: lampiran',
                $user,
                $docs->pluck('label')->sort()->values()->all() === $person->documents->pluck('type')->sort()->values()->all(),
            );
        }

        if ($skipEmployee) {
            $this->check('employees: akun non-karyawan tidak punya employee', $user, $employee === null);

            return;
        }

        $this->check('employees: baris ada', $user, $employee !== null);

        if (! $employee) {
            return;
        }

        $this->check('employees: data kepegawaian', $user, $this->same($employee, [
            'nip' => $user->nip,
            'company_id' => $user->company_id,
            'employee_type_id' => $profile?->employee_type_id,
            'job_position_id' => $profile?->job_position_id,
            'pangkat_id' => $profile?->pangkat_id,
            'kelas_id' => $profile?->kelas_id,
            'joining_date' => $profile?->joining_date,
            'tmt_tetap' => $profile?->tmt_tetap,
            'status' => ($user->is_active && ! $user->trashed()) ? 'aktif' : 'nonaktif',
        ]));

        // Dua sumber jabatan: profil (employees.job_position_id) vs riwayat aktif (user_job_positions).
        $active = $ledger->filter(fn ($r) => $r->status === 'active' && ! $r->end_date);

        $this->check('jabatan: paling banyak satu jabatan aktif di riwayat', $user, $active->count() <= 1);
        $this->check(
            'jabatan: profil = jabatan aktif di riwayat',
            $user,
            $active->count() <= 1 && $this->norm($employee->job_position_id) === $this->norm($active->first()?->job_position_id),
        );

        $this->check('riwayat: jumlah employee_careers = user_job_positions', $user, $employee->careers->count() === $ledger->count());
    }

    /** @param array<string, mixed> $expected */
    private function same(object $actual, array $expected): bool
    {
        foreach ($expected as $key => $value) {
            if ($this->norm($actual->{$key}) !== $this->norm($value)) {
                return false;
            }
        }

        return true;
    }

    /** Samakan bentuk: kosong = null, tanggal = Y-m-d, sisanya string. */
    private function norm(mixed $v): ?string
    {
        if ($v instanceof CarbonInterface) {
            return $v->format('Y-m-d');
        }

        if ($v === null || $v === '') {
            return null;
        }

        $v = (string) $v;

        return preg_match('/^\d{4}-\d{2}-\d{2}( |T|$)/', $v) ? substr($v, 0, 10) : $v;
    }

    private function check(string $dimension, User $user, bool $ok): void
    {
        $this->stats[$dimension] ??= ['checked' => 0, 'bad' => []];
        $this->stats[$dimension]['checked']++;

        if (! $ok) {
            $this->stats[$dimension]['bad'][] = $user->nip;
        }
    }

    private function report(): int
    {
        $limit = (int) $this->option('limit');

        $this->table(
            ['Dimensi', 'Dicek', 'Selisih'],
            collect($this->stats)->map(fn ($s, $d) => [$d, $s['checked'], count($s['bad'])])->values()->all(),
        );

        $total = 0;

        foreach ($this->stats as $dimension => $s) {
            if (! $s['bad']) {
                continue;
            }

            $total += count($s['bad']);
            $more = count($s['bad']) > $limit ? ' ... (+' . (count($s['bad']) - $limit) . ' lagi)' : '';
            $this->warn("{$dimension}: " . implode(', ', array_slice($s['bad'], 0, $limit)) . $more);
        }

        $this->line($total ? "Total selisih: {$total}" : 'Semua cocok.');

        return $total ? self::FAILURE : self::SUCCESS;
    }
}
