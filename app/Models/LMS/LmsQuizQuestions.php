<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @mixin IdeHelperLmsQuizQuestions
 */
class LmsQuizQuestions extends Pivot
{
    use HasUlids;

    protected $table = 'lms_quiz_questions';

    protected $fillable = [
        'quiz_id',
        'question_bank_id',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            $model->quiz?->updateTotalScore();
        });

        static::deleted(function ($model) {
            $model->quiz?->updateTotalScore();
        });
    }

    public function quiz()
    {
        return $this->belongsTo(LmsQuizzes::class, 'quiz_id');
    }

    public function questionBank()
    {
        return $this->belongsTo(QuestionBanks::class, 'question_bank_id');
    }
}
