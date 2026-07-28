<?php

namespace App\Models\LMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class LmsQuizResults extends Model
{
    use HasUlids;

    protected $table = 'lms_quiz_results';

    protected $fillable = [
        'meeting_quiz_id',
        'quiz_id',
        'respondent_id',
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

    public function respondent()
    {
        return $this->belongsTo(LmsRespondent::class, 'respondent_id', 'id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id');
    }
}
