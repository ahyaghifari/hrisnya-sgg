<?php

namespace App\Services\Rekrutmen;

use App\Models\Job\JobOpenings;
use App\Models\User;
use App\Services\Job\RekrutmenSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fase 7 — buat / sinkron akun karyawan HRIS dari data kandidat Rekrutmen (status `passed`).
 * Dedupe by email. Data diri lengkap kandidat (candidate + 8 tabel anak) ditarik dari
 * Rekrutmen; yang dipetakan ke HRIS baru kolom-kolom `user_profiles` yang cocok langsung —
 * tabel anak (keluarga/pendidikan/dll) belum diimpor (perlu padanan HRIS, follow-up).
 */
class CandidateOnboardingService
{
    /** @return array{ok:bool, message:string, user_id?:string, existing?:bool} */
    public function createEmployeeFromCandidate(string $jobApplicationId): array
    {
        $sync = app(RekrutmenSyncService::class);

        $payload = $sync->getCandidate($jobApplicationId);

        if (! $payload || empty($payload['candidate'])) {
            return ['ok' => false, 'message' => 'Data diri kandidat belum tersedia dari Rekrutmen (harus sudah onboarding/passed).'];
        }

        $c = $payload['candidate'];
        $email = $payload['job_application']['email'] ?? null;

        if (! $email) {
            return ['ok' => false, 'message' => 'Email pelamar tidak ada di data Rekrutmen.'];
        }

        $app = $sync->getApplication($jobApplicationId);
        $hrisOpeningId = $app['job_opening']['hris_job_opening_id'] ?? null;
        $opening = $hrisOpeningId ? JobOpenings::with('jobPosition')->find($hrisOpeningId) : null;

        if (! $opening) {
            return ['ok' => false, 'message' => 'Lowongan HRIS terkait tidak ditemukan — lowongan ini kemungkinan dibuat manual di Rekrutmen (tanpa hris_job_opening_id).'];
        }

        $profileData = array_filter([
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
            'joining_date'       => now()->toDateString(),
        ], fn ($v) => $v !== null && $v !== '');

        return DB::transaction(function () use ($email, $c, $opening, $profileData) {
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

            return [
                'ok'       => true,
                'existing' => $isExisting,
                'user_id'  => $user->id,
                'message'  => $isExisting
                    ? "Akun dengan email {$email} sudah ada — profil diperbarui dari data kandidat."
                    : "Akun karyawan dibuat untuk {$email}.",
            ];
        });
    }
}
