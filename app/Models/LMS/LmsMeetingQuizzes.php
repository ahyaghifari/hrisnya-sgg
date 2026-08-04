<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class LmsMeetingQuizzes extends Model
{
    use HasUlids;

    protected $table = 'lms_meeting_quizzes';

    protected $fillable = [
        'meeting_id',
        'quiz_id',
        'sort_order',
        'duration',
        'public_token',
        'is_graduation_requirement',
    ];

    public function meeting()
    {
        return $this->belongsTo(LmsMeetings::class, 'meeting_id');
    }

    public function quiz()
    {
        return $this->belongsTo(LmsQuizzes::class, 'quiz_id');
    }

    public function results()
    {
        return $this->hasMany(LmsQuizResults::class, 'meeting_quiz_id', 'id');
    }

    protected static function booted()
    {
        static::saving(function ($quiz) {
            if (is_null($quiz->sort_order)) {
            $quiz->sort_order = static::max('sort_order') + 1;
            }
        });
    }
}
