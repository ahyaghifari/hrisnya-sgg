<?php

namespace App\Models\LMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsQuizUserResults
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
}
