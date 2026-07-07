<?php

namespace App\Models\LMS\Trainer;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperTrainerAssessments
 */
class TrainerAssessments extends Model
{
    use HasUlids;

    protected $table = 'trainer_assessments';

    protected $fillable = [
        'meeting_id',
        'trainer_id',
        'score_honor',
        'score_validity',
    ];

    protected static function booted()
    {
        // Update trainer_id dan scores setelah create atau save
        static::saved(function ($trainerAssessment) {
            $trainerAssessment->updateTrainerAndScores();
        });
    }

    public function updateTrainerAndScores()
    {
        // 1. Update trainer_id dari meeting
        if ($this->meeting_id && $this->meeting) {
            $trainerId = $this->meeting->trainer_id;
            if ($trainerId && $trainerId !== $this->trainer_id) {
                $this->update(['trainer_id' => $trainerId]);
            }
        }

        // 2. Calculate score_honor (sum of honor type details)
        $scoreHonor = $this->details()
            ->whereHas('variable', function ($query) {
                $query->where('type', 'honor');
            })
            ->sum('score');

        // 3. Calculate score_validity (sum of validity type details)
        $scoreValidity = $this->details()
            ->whereHas('variable', function ($query) {
                $query->where('type', 'validity');
            })
            ->sum('score');

        // Update scores jika ada perubahan
        if ($this->score_honor != $scoreHonor || $this->score_validity != $scoreValidity) {
            $this->updateQuietly([
                'score_honor' => $scoreHonor,
                'score_validity' => $scoreValidity,
            ]);
        }
    }

    public function details()
    {
        return $this->hasMany(TrainerAssessmentDetails::class, 'assessment_id', 'id');
    }

    public function trainer()
    {
        return $this->belongsTo(Trainers::class, 'trainer_id', 'id');
    }

    public function meeting()
    {
        return $this->belongsTo(\App\Models\LMS\LMSMeetings::class, 'meeting_id', 'id');
    }
}
