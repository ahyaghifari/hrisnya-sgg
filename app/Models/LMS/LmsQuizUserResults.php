<?php

namespace App\Models\LMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsQuizUserResults
 * @property string $id
 * @property string $meeting_quiz_id
 * @property string $quiz_id
 * @property string $user_id
 * @property int $total_score
 * @property int $max_score
 * @property int $correct_count
 * @property int $wrong_count
 * @property int $is_completed
 * @property string $status
 * @property string|null $reviewed_by
 * @property string|null $reviewed_at
 * @property string|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LMS\LmsMeetingQuizzes $meetingQuiz
 * @property-read \App\Models\LMS\LmsQuizzes $quiz
 * @property-read User|null $reviewer
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereCorrectCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereIsCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereMaxScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereMeetingQuizId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereQuizId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereReviewedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereReviewedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereTotalScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizUserResults whereWrongCount($value)
 * @mixin \Eloquent
 */
class LmsQuizUserResults extends Model
{
    use HasUlids;

    protected $table = 'lms_quiz_user_results';

    protected $fillable = [
        'meeting_quiz_id',
        'quiz_id',
        'user_id',
        'total_score',
        'max_score',
        'correct_count',
        'wrong_count',
        'is_completed',
        'completed_at',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    public function meetingQuiz()
    {
        return $this->belongsTo(LmsMeetingQuizzes::class, 'meeting_quiz_id', 'id');
    }

    public function quiz()
    {
        return $this->belongsTo(LmsQuizzes::class, 'quiz_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id');
    }
}
