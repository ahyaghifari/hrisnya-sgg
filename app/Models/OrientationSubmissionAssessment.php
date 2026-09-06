<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 1 indikator penilaian orientasi pada 1 pengajuan — snapshot dari Setting `orientation_criteria`
 * saat dinilai. Skor per bulan ada di relasi `scores`. Lihat OrientationService::review().
 */
class OrientationSubmissionAssessment extends Model
{
    use HasUlids;

    protected $table = 'orientation_submission_assessments';

    protected $fillable = [
        'orientation_submission_id',
        'label',
        'comment',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(OrientationSubmission::class, 'orientation_submission_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(OrientationSubmissionScore::class, 'orientation_submission_assessment_id')
            ->orderBy('period_month');
    }
}
