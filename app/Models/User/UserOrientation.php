<?php

namespace App\Models\User;

use App\Models\Master\Companies;
use App\Models\OrientationSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 periode orientasi (ledger, mirip UserContract). Dibuat lewat Rekrutmen
 * (CandidateOnboardingService) atau perpanjangan dari penilaian orientasi. Append-only:
 * perpanjangan = baris baru (source='extension', previous_orientation_id ke baris sebelumnya).
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
        'source_submission_id',
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

    public function sourceSubmission(): BelongsTo
    {
        return $this->belongsTo(OrientationSubmission::class, 'source_submission_id');
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
