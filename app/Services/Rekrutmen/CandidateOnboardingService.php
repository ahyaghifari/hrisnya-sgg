<?php

namespace App\Services\Rekrutmen;

use App\Enum\EmployeeTypeCategory;
use App\Models\Job\JobOpenings;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
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

        $ctx = $this->resolveContext($jobApplicationId);

        if (! $ctx['ok']) {
            return $ctx;
        }

        [$c, $email, $jobPosition, $companyId, $orientationType] =
            [$ctx['candidate'], $ctx['email'], $ctx['jobPosition'], $ctx['companyId'], $ctx['orientationType']];

        // NIK saat registrasi akun pelamar (wajib & unik) lebih bisa diandalkan daripada
        // NIK yang diisi ulang kandidat di form data diri — pakai itu dulu, fallback ke
        // Candidate::nik buat akun lama sebelum kolom users.nik ada.
        $nik = $ctx['app']->user?->nik ?: $c->nik;

        // Tanggal Bergabung = tanggal onboarding yang ditetapkan recruiter (TtdOnboardingPage);
        // fallback ke hari ini kalau belum diatur.
        $joiningDate = $ctx['app']->onboarding_date
            ? Carbon::parse($ctx['app']->onboarding_date)->toDateString()
            : now()->toDateString();

        $profileData = array_filter([
            'employee_type_id'   => $orientationType->id,
            'nickname'            => $c->nickname,
            'marital_status'      => $c->status_marital,
            'no_nik'             => $nik,
            'no_kk'              => $c->no_kk,
            'no_bpjs_kes'        => $c->no_bpjskes,
            'no_bpjs_ket'        => $c->no_bpjstk,
            'bank_account_number' => $c->bsi_bank_number,
            'bank_account_name'  => $c->name,
            'address'            => $c->ktp_address,
            'village'            => $c->village,
            'district'           => $c->district,
            'city'               => $c->city,
            'province'           => $c->province,
            'job_position_id'    => $jobPosition->id,
            'joining_date'       => $joiningDate,
        ], fn ($v) => $v !== null && $v !== '');

        return DB::transaction(function () use ($email, $c, $companyId, $profileData, $joiningDate, $orientationMonths) {
            $existing = User::withTrashed()->where('email', $email)->first();
            $isExisting = (bool) $existing;

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $user = $existing;
            } else {
                $nipGenerator = app(\App\Services\Kepegawaian\NipGeneratorService::class);

                $user = User::create([
                    'name'       => $c->name ?? $email,
                    'email'      => $email,
                    'password'   => bcrypt(Str::password(16)),
                    'company_id' => $companyId,
                    'is_active'  => true,
                    'nip'        => $nipGenerator->applies($companyId) ? $nipGenerator->generate($joiningDate) : null,
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
                    'company_id' => $companyId,
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

    /**
     * Ringkasan data yang akan dipakai bikin akun — buat ditampilkan di modal sebelum konfirmasi.
     *
     * @return array{
     *   ok:bool, message?:string, email?:string, company?:?string, jabatan?:?string,
     *   divisi?:?string, orientationType?:?string, existingUser?:bool,
     *   profile?:list<array{label:string, value:?string, required:bool}>, missing?:list<string>
     * }
     */
    public function previewOnboarding(string $jobApplicationId): array
    {
        $ctx = $this->resolveContext($jobApplicationId);

        if (! $ctx['ok']) {
            return $ctx;
        }

        /** @var \App\Models\Rekrutmen\Candidate $c */
        $c = $ctx['candidate'];
        $jp = $ctx['jobPosition'];

        // Sama seperti createEmployeeFromCandidate: NIK registrasi diutamakan.
        $c->nik = $ctx['app']->user?->nik ?: $c->nik;

        $rows = [
            ['label' => 'Nama sesuai KTP', 'key' => 'name',            'required' => true],
            ['label' => 'Nama panggilan',  'key' => 'nickname',        'required' => false],
            ['label' => 'NIK',             'key' => 'nik',             'required' => true],
            ['label' => 'No. Kartu Keluarga', 'key' => 'no_kk',       'required' => true],
            ['label' => 'Nama ibu kandung', 'key' => 'mother_name',    'required' => true],
            ['label' => 'Status pernikahan', 'key' => 'status_marital', 'required' => true],
            ['label' => 'Alamat KTP',      'key' => 'ktp_address',     'required' => true],
            ['label' => 'Kelurahan/Desa',  'key' => 'village',         'required' => true],
            ['label' => 'Kecamatan',       'key' => 'district',        'required' => true],
            ['label' => 'Kota/Kabupaten',  'key' => 'city',            'required' => true],
            ['label' => 'Provinsi',        'key' => 'province',        'required' => true],
            ['label' => 'No. Rekening (BSI)', 'key' => 'bsi_bank_number', 'required' => true],
            ['label' => 'BPJS Kesehatan',  'key' => 'no_bpjskes',      'required' => false],
            ['label' => 'BPJS Ketenagakerjaan', 'key' => 'no_bpjstk',  'required' => false],
        ];

        $profile = [];
        $missing = [];

        foreach ($rows as $r) {
            $val = trim((string) ($c->{$r['key']} ?? ''));
            $profile[] = ['label' => $r['label'], 'value' => $val !== '' ? $val : null, 'required' => $r['required']];

            if ($r['required'] && $val === '') {
                $missing[] = $r['label'];
            }
        }

        return [
            'ok' => true,
            'email' => $ctx['email'],
            'company' => $ctx['company']?->name,
            'jabatan' => $jp->name,
            'divisi' => $jp->division?->name ?? $jp->orgStructure?->name,
            'orientationType' => $ctx['orientationType']->name,
            'onboardingDate' => $ctx['app']->onboarding_date
                ? \Carbon\Carbon::parse($ctx['app']->onboarding_date)->translatedFormat('l, d F Y')
                : null,
            'existingUser' => User::withTrashed()->where('email', $ctx['email'])->exists(),
            'profile' => $profile,
            'missing' => $missing,
        ];
    }

    /**
     * Validasi + resolusi jabatan/company/tipe orientasi. Dipakai bareng oleh preview & create.
     *
     * @return array{ok:bool, message?:string, app?:JobApplication, candidate?:mixed, email?:string,
     *   jobPosition?:JobPositions, company?:mixed, companyId?:string, orientationType?:EmployeeTypes}
     */
    private function resolveContext(string $jobApplicationId): array
    {
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
        $opening = $hrisOpeningId
            ? JobOpenings::with(['jobPosition.orgStructure', 'jobPosition.company', 'company'])->find($hrisOpeningId)
            : null;

        // Lowongan mode "bebas" (dibuat manual di Rekrutmen) tak punya hris_job_opening_id —
        // fallback ke job_position_id yang dipilih dari master jabatan HRIS di form lowongan.
        $jobPosition = $opening?->jobPosition
            ?? ($app->jobOpening?->job_position_id
                ? JobPositions::with(['orgStructure', 'company'])->find($app->jobOpening->job_position_id)
                : null);

        if (! $jobPosition) {
            return ['ok' => false, 'message' => 'Jabatan HRIS untuk lowongan ini belum di-set — buka lowongan di Rekrutmen dan pilih jabatan HRIS-nya dulu.'];
        }

        $companyId = $opening?->company_id ?? $jobPosition->company_id;
        $company = $opening?->company ?? $jobPosition->company;

        $orientationType = EmployeeTypes::where('company_id', $companyId)
            ->where('category', EmployeeTypeCategory::ORIENTASI->value)
            ->first();

        if (! $orientationType) {
            return ['ok' => false, 'message' => 'Tipe karyawan kategori Orientasi belum ada untuk perusahaan ini. Hubungi admin (jalankan OrientationEmployeeTypeSeeder).'];
        }

        return [
            'ok' => true,
            'app' => $app,
            'candidate' => $c,
            'email' => $email,
            'jobPosition' => $jobPosition,
            'company' => $company,
            'companyId' => $companyId,
            'orientationType' => $orientationType,
        ];
    }
}
