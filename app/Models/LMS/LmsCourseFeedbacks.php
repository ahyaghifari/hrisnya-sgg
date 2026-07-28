<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class LmsCourseFeedbacks extends Model
{
    use HasUlids;

    protected $table = 'lms_course_feedbacks';

    protected $fillable = [
        'course_id',
        'respondent_id',
        'rating',
        'feedback',
    ];

    public function course()
    {
        return $this->belongsTo(LmsCourses::class, 'course_id');
    }

    public function respondent()
    {
        return $this->belongsTo(LmsRespondent::class, 'respondent_id');
    }

    public function answers()
    {
        return $this->hasMany(LmsCourseFeedbackAnswer::class, 'course_feedback_id');
    }
}
