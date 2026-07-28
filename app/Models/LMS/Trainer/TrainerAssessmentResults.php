<?php

namespace App\Models\LMS\Trainer;

use App\Models\LMS\LmsCourses;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperTrainerAssessmentResults
 * @property string $id
 * @property string|null $company_id
 * @property string $course_id
 * @property string $trainer_id
 * @property numeric $honor_amount
 * @property int $validity_months
 * @property string|null $valid_until
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies|null $company
 * @property-read LmsCourses|null $course
 * @property-read \App\Models\LMS\Trainer\Trainers $trainer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainerAssessmentResults newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainerAssessmentResults newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainerAssessmentResults query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainerAssessmentResults whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainerAssessmentResults whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainerAssessmentResults whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainerAssessmentResults whereHonorAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainerAssessmentResults whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainerAssessmentResults whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainerAssessmentResults whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainerAssessmentResults whereValidUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrainerAssessmentResults whereValidityMonths($value)
 * @mixin \Eloquent
 */
class TrainerAssessmentResults extends Model
{
    use HasUlids;

    protected $table = 'trainer_assessment_results';


    protected $fillable = [
        'company_id',
        'course_id',
        'trainer_id',
        'honor_amount',
        'validity_months',
        'valid_until',
    ];

    public function course()
    {
        return $this->belongsTo(LmsCourses::class, 'course_id', 'id');
    }

    public function trainer()
    {
        return $this->belongsTo(Trainers::class, 'trainer_id', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id', 'id');
    }
}
