<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Skor 1 indikator orientasi pada 1 bulan periode. Lihat OrientationSubmissionAssessment. */
class OrientationSubmissionScore extends Model
{
    use HasUlids;

    protected $table = 'orientation_submission_scores';

    protected $fillable = [
        'orientation_submission_assessment_id',
        'period_month',
        'score',
        'comment',
    ];

    protected $casts = [
        'period_month' => 'date',
        'score' => 'decimal:2',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(OrientationSubmissionAssessment::class, 'orientation_submission_assessment_id');
    }
}
