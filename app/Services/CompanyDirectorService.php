<?php

namespace App\Services;

use App\Models\Master\CompanyDirector;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Support\Collection;

/**
 * Satu sumber kebenaran "siapa Direktur perusahaan ini". Sebelumnya lewat Setting
 * `sikartap_direktur_job_position_id`; sekarang tabel `company_directors` (1 jabatan / company).
 * Dipakai SikartapDirectorDecisionService dan modul lain yang butuh jabatan Direktur.
 */
class CompanyDirectorService
{
    /** job_position_id Direktur untuk 1 company, atau null kalau belum diatur. */
    public function jobPositionId(?string $companyId): ?string
    {
        return $companyId
            ? CompanyDirector::where('company_id', $companyId)->value('job_position_id')
            : null;
    }

    /**
     * Pemegang aktif jabatan Direktur perusahaan ini (bisa lebih dari 1 orang kalau jabatannya
     * di-assign ke beberapa user).
     *
     * @return Collection<int, User>
     */
    public function holders(?string $companyId): Collection
    {
        $jobPositionId = $this->jobPositionId($companyId);

        if (! $jobPositionId) {
            return collect();
        }

        return UserJobPositions::query()
            ->where('job_position_id', $jobPositionId)
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();
    }

    public function isDirector(?string $companyId, User $user): bool
    {
        return $this->holders($companyId)->contains('id', $user->id);
    }

    /** Set/ganti jabatan Direktur 1 company (dipakai Pengaturan Sikartap). */
    public function setJobPosition(string $companyId, string $jobPositionId): void
    {
        CompanyDirector::updateOrCreate(
            ['company_id' => $companyId],
            ['job_position_id' => $jobPositionId],
        );
    }
}
