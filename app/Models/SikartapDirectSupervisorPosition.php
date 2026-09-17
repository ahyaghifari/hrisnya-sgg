<?php

namespace App\Models;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penanda jabatan "dinilai atasan langsung" (per context, company) — alternatif skema
 * by-Departemen di SikartapReviewer. Lihat SikartapReviewerResolver.
 */
class SikartapDirectSupervisorPosition extends Model
{
    use HasUlids;

    protected $table = 'sikartap_direct_supervisor_positions';

    protected $fillable = [
        'context',
        'company_id',
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

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPositions::class, 'job_position_id');
    }
}
