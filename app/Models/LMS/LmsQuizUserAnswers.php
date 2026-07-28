<?php

namespace App\Models\LMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsQuizUserAnswers
 * @property string $id
 * @property string $meeting_quiz_id
 * @property string $quiz_id
 * @property string $question_id
 * @property string $user_id
 * @property string|null $user_answer
 * @property int $is_correct
 * @property int $earned_points
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LMS\LmsMeetingQuizzes $meetingQuiz
 * @property-read \App\Models\LMS\LmsQuestionBanks $question
 * @property-read \App\Models\LMS\LmsQuizzes $quiz
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserAnswers newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserAnswers newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserAnswers query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserAnswers whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserAnswers whereEarnedPoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserAnswers whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserAnswers whereIsCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserAnswers whereMeetingQuizId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserAnswers whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserAnswers whereQuizId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserAnswers whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserAnswers whereUserAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserAnswers whereUserId($value)
 * @mixin \Eloquent
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
        return $this->belongsTo(LmsQuestionBanks::class, 'question_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
