<?php

namespace App\Services\Rekrutmen;

use App\Enum\EmployeeTypeCategory;
use App\Models\Job\JobOpenings;
use App\Models\Master\EmployeeTypes;
use App\Models\Rekrutmen\JobApplication;
use App\Models\User;
use App\Models\User\UserOrientation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fase 7 — buat / sinkron akun karyawan HRIS dari data kandidat Rekrutmen (status `passed`).
 * Dedupe by email. Data diri kandidat dibaca LANGSUNG dari DB `recruitment` (shared DB,
 * koneksi `recruitment`) — bukan HTTP lagi. Yang dipetakan ke HRIS baru kolom-kolom
 * `user_profiles` yang cocok langsung; tabel anak (keluarga/pendidikan/dll) belum diimpor.
 */
class CandidateOnboardingService
{
    /** Preset durasi orientasi (bulan) — dipilih HR di modal onboarding. */
    public const ORIENTATION_MONTHS_OPTIONS = [3, 6, 12];

    /**
     * @param  int  $orientationMonths  Lama orientasi (3/6/12). Baris user_orientations dibuat
     *   dari sini — ini SATU-SATUNYA jalur pembuatan periode orientasi (tidak lewat "Tambah
     *   Karyawan" manual).
     * @return array{ok:bool, message:string, user_id?:string, existing?:bool}
     */
    public function createEmployeeFromCandidate(string $jobApplicationId, int $orientationMonths = 3): array
    {
        if (! in_array($orientationMonths, self::ORIENTATION_MONTHS_OPTIONS, true)) {
            return ['ok' => false, 'message' => 'Lama orientasi harus 3, 6, atau 12 bulan.'];
        }

        $app = JobApplication::with(['candidate', 'user', 'jobOpening'])->find($jobApplicationId);

        if (! $app) {
            return ['ok' => false, 'message' => 'Lamaran tidak ditemukan di DB recruitment.'];
        }

        $c = $app->candidate;

        if (! $c) {
            return ['ok' => false, 'message' => 'Data diri kandidat belum tersedia (kandidat harus sudah onboarding/passed).'];
        }

        $email = $app->user?->email;

        if (! $email) {
            return ['ok' => false, 'message' => 'Email pelamar tidak ada di data Rekrutmen.'];
        }

        $hrisOpeningId = $app->jobOpening?->hris_job_opening_id;
        $opening = $hrisOpeningId ? JobOpenings::with('jobPosition')->find($hrisOpeningId) : null;

        if (! $opening) {
            return ['ok' => false, 'message' => 'Lowongan HRIS terkait tidak ditemukan — lowongan ini kemungkinan dibuat manual di Rekrutmen (tanpa hris_job_opening_id).'];
        }

        // Tipe karyawan Orientasi milik company ini (dipastikan ada lewat OrientationEmployeeTypeSeeder).
        $orientationType = EmployeeTypes::where('company_id', $opening->company_id)
            ->where('category', EmployeeTypeCategory::ORIENTASI->value)
            ->first();

        if (! $orientationType) {
            return ['ok' => false, 'message' => 'Tipe karyawan kategori Orientasi belum ada untuk perusahaan ini. Hubungi admin (jalankan OrientationEmployeeTypeSeeder).'];
        }

        $joiningDate = now()->toDateString();

        $profileData = array_filter([
            'employee_type_id'   => $orientationType->id,
            'nickname'            => $c['nickname'] ?? null,
            'marital_status'      => $c['status_marital'] ?? null,
            'no_nik'             => $c['nik'] ?? null,
            'no_kk'              => $c['no_kk'] ?? null,
            'no_bpjs_kes'        => $c['no_bpjskes'] ?? null,
            'no_bpjs_ket'        => $c['no_bpjstk'] ?? null,
            'bank_account_number' => $c['bsi_bank_number'] ?? null,
            'bank_account_name'  => $c['name'] ?? null,
            'address'            => $c['ktp_address'] ?? null,
            'village'            => $c['village'] ?? null,
            'district'           => $c['district'] ?? null,
            'city'               => $c['city'] ?? null,
            'province'           => $c['province'] ?? null,
            'job_position_id'    => $opening->job_position_id,
            'org_structure_id'   => $opening->jobPosition?->org_structure_id,
            'joining_date'       => $joiningDate,
        ], fn ($v) => $v !== null && $v !== '');

        return DB::transaction(function () use ($email, $c, $opening, $profileData, $joiningDate, $orientationMonths) {
            $existing = User::withTrashed()->where('email', $email)->first();
            $isExisting = (bool) $existing;

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $user = $existing;
            } else {
                $user = User::create([
                    'name'       => $c['name'] ?? $email,
                    'email'      => $email,
                    'password'   => bcrypt(Str::password(16)),
                    'company_id' => $opening->company_id,
                    'is_active'  => true,
                ]);
            }

            $user->profile()->updateOrCreate(['user_id' => $user->id], $profileData);

            // Periode orientasi — cuma dibuat kalau karyawan ini belum punya periode orientasi
            // aktif (hindari dobel kalau tombol onboarding dipencet ulang).
            $hasActiveOrientation = UserOrientation::where('user_id', $user->id)
                ->whereDate('end_date', '>=', $joiningDate)
                ->exists();

            if (! $hasActiveOrientation) {
                UserOrientation::create([
                    'user_id' => $user->id,
                    'company_id' => $opening->company_id,
                    'start_date' => $joiningDate,
                    'end_date' => Carbon::parse($joiningDate)->addMonths($orientationMonths)->toDateString(),
                    'duration_months' => $orientationMonths,
                    'source' => 'recruitment',
                ]);
            }

            return [
                'ok'       => true,
                'existing' => $isExisting,
                'user_id'  => $user->id,
                'message'  => $isExisting
                    ? "Akun dengan email {$email} sudah ada — profil & periode orientasi ({$orientationMonths} bln) diperbarui dari data kandidat."
                    : "Akun karyawan dibuat untuk {$email} dengan masa orientasi {$orientationMonths} bulan.",
            ];
        });
    }
}
