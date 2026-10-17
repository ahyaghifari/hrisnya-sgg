<?php

namespace App\Console\Commands;

use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeCareer;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfiles;
use App\Services\Identity\EmployeeSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Salin users + user_profiles + user_job_positions + user_profile_documents ke employees,
 * employee_identities, employee_addresses, employee_documents, dan employee_careers (lihat
 * EmployeeSyncService). Idempotent - aman diulang. --dry-run tidak menulis apa pun, cuma menghitung
 * & melaporkan konflik data (NIK kosong/ganda) yang perlu dibereskan sebelum constraint unik
 * dipasang.
 */
class BackfillEmployees extends Command
{
    protected $signature = 'identity:backfill {--dry-run : Hitung & laporkan saja, tanpa menulis} {--user= : Batasi ke satu NIP} {--company= : Batasi ke satu perusahaan (nama atau kode)}';

    /** Nama lama (sebelum persons dilebur ke employees) tetap jalan. */
    protected $aliases = ['identity:backfill-employees'];

    protected $description = 'Salin ke employees + identitas/alamat/lampiran + employee_careers (idempotent)';

    public function handle(EmployeeSyncService $sync): int
    {
        $dry = (bool) $this->option('dry-run');

        $companyId = null;

        if ($input = $this->option('company')) {
            $companyId = \App\Models\Master\Companies::where('name', $input)->orWhere('code', $input)->value('id');

            if (! $companyId) {
                $this->error("Perusahaan '{$input}' tidak ditemukan.");
                return self::FAILURE;
            }
        }

        // users.company_id (masa transisi) masih jadi sumber bagi user yang belum punya employee.
        $legacyColumn = \Illuminate\Support\Facades\Schema::hasColumn('users', 'company_id');

        $users = User::withoutGlobalScopes()->withTrashed()
            ->when($this->option('user'), fn ($q, $nip) => $q->whereNip($nip))
            ->when($companyId, fn ($q, $id) => $q->where(function ($w) use ($id, $legacyColumn) {
                $w->whereCompany($id);
                $legacyColumn && $w->orWhere('users.company_id', $id);
            }));

        $total = (clone $users)->count();
        $existing = Employee::whereIn('user_id', (clone $users)->select('id'))->count();
        $ledger = UserJobPositions::whereIn('user_id', (clone $users)->select('id'))->count();

        $this->info(($dry ? '[DRY-RUN] ' : '') . "karyawan: {$total}, sudah punya employee: {$existing}, akan dibuat: " . ($total - $existing));
        $this->line("baris riwayat jabatan (user_job_positions): {$ledger}, sudah tersalin: " . EmployeeCareer::count());

        $this->reportConflicts();

        if ($dry) {
            return self::SUCCESS;
        }

        $failed = 0;

        $users->chunkById(200, function ($chunk) use ($sync, &$failed) {
            foreach ($chunk as $user) {
                try {
                    $sync->syncUser($user);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("NIP {$user->nip}: {$e->getMessage()}");
                }
            }
        });

        $this->line('employee_id payroll diperbaiki: ' . $sync->linkPayrollEmployeeIds() . ' baris');
        $this->info('Selesai. Gagal: ' . $failed);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Konflik data yang harus diputuskan HC/Admin sebelum NIK dibuat unik. Hanya jumlah & NIP. */
    private function reportConflicts(): void
    {
        $blank = UserProfiles::whereNull('no_nik')->orWhere('no_nik', '')->count();
        $this->line("NIK kosong: {$blank}");

        $dupes = UserProfiles::select('no_nik', DB::raw('count(*) as n'))
            ->whereNotNull('no_nik')->where('no_nik', '!=', '')
            ->groupBy('no_nik')->havingRaw('count(*) > 1')->get();

        $this->line('NIK ganda: ' . $dupes->count() . ' nilai');

        foreach ($dupes as $d) {
            $nips = User::withoutGlobalScopes()->withTrashed()
                ->whereIn('id', UserProfiles::where('no_nik', $d->no_nik)->pluck('user_id'))
                ->pluck('nip')->implode(', ');
            $this->warn("  NIP berbagi NIK: {$nips}");
        }
    }
}
