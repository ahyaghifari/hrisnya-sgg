<?php

namespace App\Models\LMS\Trainer;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperTrainerAssessmentDetails
 */
class TrainerAssessmentDetails extends Model
{
    use HasUlids;

    protected $table = 'trainer_assessment_details';

    protected $fillable = [
        'assessment_id',
        'variable_id',
        'category_id',
        'type',
        'score',
    ];

    protected static function booted()
    {
        // Trigger update scores di parent assessment setelah detail disimpan
        static::saved(function ($detail) {
            if ($detail->assessment) {
                $detail->assessment->updateTrainerAndScores();
            }
        });

        // Trigger update scores di parent assessment setelah detail dihapus
        static::deleted(function ($detail) {
            if ($detail->assessment) {
                $detail->assessment->updateTrainerAndScores();
            }
        });
    }

    public function assessment()
    {
        return $this->belongsTo(TrainerAssessments::class, 'assessment_id', 'id');
    }

    public function variable()
    {
        return $this->belongsTo(TrainerScoreVariables::class, 'variable_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(TrainerScoreCategories::class, 'category_id', 'id');
    }
}