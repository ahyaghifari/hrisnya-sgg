<?php

namespace App\Services\Identity;

use App\Enum\OrgStructurLevel;
use App\Models\Employee\Employee;
use App\Models\Master\JobPositions;
use App\Models\User\UserProfiles;
use Illuminate\Database\Eloquent\Builder;

/**
 * Titik akses tunggal data kepegawaian user (jabatan, organisasi) untuk fitur yang sudah
 * dipindah dari user_profiles ke tabel employees (roadmap_migrasi_bertahap_identity_hris.md).
 * Mengganti sumber cukup di sini - itu juga cara rollback.
 *
 * Jatuh balik ke user_profiles kalau employees tidak punya nilainya, supaya perilaku lama TIDAK
 * berubah untuk akun tanpa baris employees (Super Admin/Direktur, dokter non-karyawan) atau
 * kalau salinan belum tersinkron. Selisih salinan dilaporkan `identity:verify`. Hapus fallback
 * ini saat user_profiles dilepas.
 */
class EmployeeContext
{
    public static function jobPositionId(?string $userId): ?string
    {
        if (! $userId) {
            return null;
        }

        return Employee::where('user_id', $userId)->value('job_position_id')
            ?? UserProfiles::where('user_id', $userId)->value('job_position_id');
    }

    /**
     * Batasi query User ke yang jabatannya salah satu dari $jobPositionIds: employees dulu,
     * jatuh balik ke profil hanya untuk akun yang employees-nya tidak punya jabatan (sama dengan
     * aturan jobPositionId()). Dipakai pencarian penyetuju tipe job_position.
     *
     * @param  array<int, string>  $jobPositionIds
     */
    public static function whereJobPositionIn(Builder $query, array $jobPositionIds): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereHas('employee', fn ($e) => $e->whereIn('job_position_id', $jobPositionIds))
            ->orWhere(fn ($q2) => $q2
                ->whereDoesntHave('employee', fn ($e) => $e->whereNotNull('job_position_id'))
                ->whereHas('profile', fn ($p) => $p->whereIn('job_position_id', $jobPositionIds))));
    }

    /** Batasi query User ke yang organisasi jabatannya persis $orgStructureId (bukan turunannya). */
    public static function whereOrgStructure(Builder $query, string $orgStructureId): Builder
    {
        return self::whereJobPositionIn(
            $query,
            JobPositions::where('org_structure_id', $orgStructureId)->pluck('id')->all(),
        );
    }

    /** Struktur organisasi milik jabatan user (bukan departemen). */
    public static function orgStructureId(?string $userId): ?string
    {
        $jobId = self::jobPositionId($userId);

        return $jobId ? JobPositions::find($jobId)?->orgStructure?->id : null;
    }

    /** Departemen user: sama dengan UserProfiles::getDepartmentAttribute (diturunkan dari organisasi jabatan). */
    public static function departmentId(?string $userId): ?string
    {
        $jobId = self::jobPositionId($userId);
        $org = $jobId ? JobPositions::find($jobId)?->orgStructure : null;

        if (! $org) {
            return null;
        }

        $department = $org->level === OrgStructurLevel::LEVEL_DEPARTMENT->value
            ? $org
            : ($org->parent?->parent ?? $org->parent);

        return $department?->id;
    }
}
