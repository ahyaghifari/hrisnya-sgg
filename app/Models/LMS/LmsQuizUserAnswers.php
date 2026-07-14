<?php

namespace App\Models\LMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsQuizUserAnswers
 */
class LmsQuizUserAnswers extends Model
{
    use HasUlids;

    protected $table = 'lms_quiz_user_answers';

    protected $fillable = [
        'meeting_quiz_id',
        'quiz_id',
        'question_id',
        'user_id',
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
        return $this->belongsTo(QuestionBanks::class, 'question_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
