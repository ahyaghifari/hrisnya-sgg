<?php

namespace App\Models\User;

use App\Models\Master\Companies;
use App\Models\OrientationSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 1 periode orientasi (ledger, mirip UserContract). Dibuat lewat Rekrutmen
 * (CandidateOnboardingService) atau perpanjangan dari penilaian orientasi. Append-only:
 * perpanjangan = baris baru (source='extension', previous_orientation_id ke baris sebelumnya).
 *
 * INDUK dari orientation_submissions (1 periode : N pengajuan penilaian).
 */
class UserOrientation extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'company_id',
        'start_date',
        'end_date',
        'duration_months',
        'source',
        'previous_orientation_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_months' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    /** Pengajuan penilaian untuk periode ini (anak). */
    public function submissions(): HasMany
    {
        return $this->hasMany(OrientationSubmission::class)->latest();
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_orientation_id');
    }

    public function isActive(): bool
    {
        return $this->end_date && ! $this->end_date->isPast();
    }
}
