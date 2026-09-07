<?php

namespace App\Models;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penunjukan penilai per (context, company, departemen level-1, jabatan). General — lintas
 * milestone perjalanan status karyawan. Reviewer = JABATAN, di-resolve ke pemegang aktif oleh
 * App\Services\SikartapReviewerResolver. Lihat migration create_sikartap_reviewers_table.
 */
class SikartapReviewer extends Model
{
    use HasUlids;

    /**
     * Penilai perjalanan status karyawan — Orientasi & Perpanjangan Kontrak digabung jadi satu
     * set penilai per (company, departemen, jabatan) (2026-09-07, instruksi user).
     */
    public const CONTEXT_STATUS_KARYAWAN = 'status_karyawan';

    /** @deprecated pakai CONTEXT_STATUS_KARYAWAN — dialias supaya kode/tes lama tetap jalan. */
    public const CONTEXT_ORIENTATION = self::CONTEXT_STATUS_KARYAWAN;

    /** @deprecated pakai CONTEXT_STATUS_KARYAWAN. */
    public const CONTEXT_CONTRACT = self::CONTEXT_STATUS_KARYAWAN;

    public const CONTEXT_SIKARTAP_IDEA = 'sikartap_idea';

    protected $table = 'sikartap_reviewers';

    protected $fillable = [
        'context',
        'company_id',
        'org_structure_id',
        'job_position_id',
    ];

    public function scopeForContext(Builder $query, string $context): Builder
    {
        return $query->where('context', $context);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function orgStructure(): BelongsTo
    {
        return $this->belongsTo(OrgStructure::class, 'org_structure_id');
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPositions::class, 'job_position_id');
    }
}
