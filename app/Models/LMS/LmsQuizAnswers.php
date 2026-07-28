<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class LmsQuizAnswers extends Model
{
    use HasUlids;

    protected $table = 'lms_quiz_answers';

    protected $fillable = [
        'meeting_quiz_id',
        'quiz_id',
        'question_id',
        'respondent_id',
        'user_answer',
        'is_correct',
        'earned_points',
    ];

    public function meetingQuiz()
    {
        return $this->belongsTo(LmsMeetingQuizzes::class, 'meeting_quiz_id', 'id');
    }

    public function quiz()
    {
        return $this->belongsTo(LmsQuizzes::class, 'quiz_id', 'id');
    }

    public function question()
    {
        return $this->belongsTo(LmsQuestionBanks::class, 'question_id', 'id');
    }

    public function respondent()
    {
        return $this->belongsTo(LmsRespondent::class, 'respondent_id', 'id');
    }
}
