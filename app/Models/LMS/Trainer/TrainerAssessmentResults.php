<?php

namespace App\Models\LMS\Trainer;

use App\Models\LMS\LmsCourses;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
