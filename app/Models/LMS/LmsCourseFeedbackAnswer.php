<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class LmsCourseFeedbackAnswer extends Model
{
    use HasUlids;

    protected $table = 'lms_course_feedback_answers';

    protected $fillable = [
        'course_feedback_id',
        'question_id',
        'score',
    ];

    public function courseFeedback()
    {
        return $this->belongsTo(LmsCourseFeedbacks::class, 'course_feedback_id');
    }

    public function question()
    {
        return $this->belongsTo(LmsFeedbackQuestion::class, 'question_id');
    }
}
