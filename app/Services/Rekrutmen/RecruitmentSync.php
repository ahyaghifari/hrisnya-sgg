<?php

namespace App\Services\Rekrutmen;

use App\Models\Job\JobOpenings;
use App\Models\Master\Companies;
use App\Models\Rekrutmen\Company as RecruitmentCompany;
use App\Models\Rekrutmen\JobOpening as RecruitmentJobOpening;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Jembatan HRIS -> DB `recruitment` (shared DB, koneksi `recruitment`). Pengganti
 * RekrutmenSyncService yang dulu lewat HTTP ke app Rekrutmen. Semua tulis di sini
 * NON-FATAL: gagal cuma dicatat log, aksi HRIS (simpan perusahaan / lowongan) tetap jalan.
 * Repo `rekrutmen` tetap pemilik skema — kolom di-upsert by `hris_code` / `hris_job_opening_id`.
 */
class RecruitmentSync
{
    /** Upsert perusahaan HRIS ke tabel `companies` recruitment (by hris_code). */
    public function pushCompany(Companies $company): array
    {
        try {
            RecruitmentCompany::updateOrCreate(
                ['hris_code' => $company->code],
                ['name' => $company->name, 'is_active' => (bool) $company->is_active],
            );

            return ['ok' => true, 'message' => "Perusahaan '{$company->name}' disinkron ke DB recruitment."];
        } catch (\Throwable $e) {
            Log::warning('RecruitmentSync: pushCompany gagal.', ['company_id' => $company->id, 'message' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Gagal sinkron perusahaan ke DB recruitment: ' . $e->getMessage()];
        }
    }

    /** Upsert lowongan HRIS ke tabel `job_openings` recruitment (by hris_job_opening_id). */
    public function sync(JobOpenings $jobOpening): array
    {
        try {
            $jobOpening->loadMissing(['jobPosition', 'company']);

            $company = $jobOpening->company?->code
                ? RecruitmentCompany::where('hris_code', $jobOpening->company->code)->first()
                : null;

            if (! $company) {
                $message = "Perusahaan (kode {$jobOpening->company?->code}) belum ada di DB recruitment.";
                Log::warning('RecruitmentSync: sync lowongan gagal — company tak ketemu.', [
                    'job_opening_id' => $jobOpening->id, 'company_code' => $jobOpening->company?->code,
                ]);

                return ['ok' => false, 'message' => $message];
            }

            RecruitmentJobOpening::withTrashed()->updateOrCreate(
                ['hris_job_opening_id' => $jobOpening->id],
                [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'title' => $jobOpening->display_name,
                    'quota' => $jobOpening->quota,
                    'max_applicants' => $jobOpening->max_applicants,
                    'applications_deadline' => $jobOpening->application_deadline?->format('Y-m-d'),
                    'description' => $jobOpening->description,
                    'requirements' => $jobOpening->requirements,
                    'is_active' => $jobOpening->is_active,
                ],
            );

            return ['ok' => true, 'message' => "Lowongan '{$jobOpening->display_name}' disinkron ke DB recruitment."];
        } catch (\Throwable $e) {
            Log::warning('RecruitmentSync: sync lowongan gagal.', ['job_opening_id' => $jobOpening->id, 'message' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Gagal sinkron lowongan ke DB recruitment: ' . $e->getMessage()];
        }
    }

    /** Ping koneksi DB recruitment (tombol "Tes Koneksi"). */
    public function testConnection(): array
    {
        try {
            DB::connection('recruitment')->getPdo();

            return ['ok' => true, 'message' => 'Terhubung ke DB recruitment.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa terhubung ke DB recruitment: ' . $e->getMessage()];
        }
    }

    /**
     * Lowongan di recruitment yang belum terhubung ke HRIS (hris_job_opening_id null).
     *
     * @return list<array{id:string,title:?string,company_name:?string,hris_company_code:?string,quota:?int,max_applicants:?int,applications_deadline:?string,is_active:bool,created_at:?string}>
     */
    public function getUnlinkedJobOpenings(): array
    {
        try {
            return RecruitmentJobOpening::withoutTrashed()
                ->whereNull('hris_job_opening_id')
                ->with('company:id,hris_code,name')
                ->latest()
                ->get()
                ->map(fn (RecruitmentJobOpening $j) => [
                    'id' => $j->id,
                    'title' => $j->title,
                    'company_name' => $j->company?->name ?? $j->company_name,
                    'hris_company_code' => $j->company?->hris_code,
                    'quota' => $j->quota,
                    'max_applicants' => $j->max_applicants,
                    'applications_deadline' => $j->applications_deadline?->format('Y-m-d'),
                    'is_active' => (bool) $j->is_active,
                    'created_at' => $j->created_at?->toIso8601String(),
                ])
                ->all();
        } catch (\Throwable $e) {
            Log::warning('RecruitmentSync: getUnlinkedJobOpenings gagal.', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Jumlah pelamar per lowongan HRIS (batch, hindari N+1).
     *
     * @param  string[]  $hrisJobOpeningIds
     * @return array<string, int> hris_job_opening_id => jumlah pelamar
     */
    public function getApplicantCounts(array $hrisJobOpeningIds): array
    {
        if (! $hrisJobOpeningIds) {
            return [];
        }

        try {
            return RecruitmentJobOpening::whereIn('hris_job_opening_id', array_values($hrisJobOpeningIds))
                ->withCount('applications')
                ->get(['id', 'hris_job_opening_id'])
                ->mapWithKeys(fn ($j) => [$j->hris_job_opening_id => (int) $j->applications_count])
                ->all();
        } catch (\Throwable $e) {
            Log::warning('RecruitmentSync: getApplicantCounts gagal.', ['message' => $e->getMessage()]);

            return [];
        }
    }
}
