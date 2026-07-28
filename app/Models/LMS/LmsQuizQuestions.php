<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @mixin IdeHelperLmsQuizQuestions
 * @property string $id
 * @property string $quiz_id
 * @property string $question_bank_id
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LMS\LmsQuestionBanks $questionBank
 * @property-read \App\Models\LMS\LmsQuizzes $quiz
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizQuestions newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizQuestions newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizQuestions query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizQuestions whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizQuestions whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizQuestions whereQuestionBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizQuestions whereQuizId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizQuestions whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuizQuestions whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LmsQuizQuestions extends Pivot
{
    use HasUlids;

    protected $table = 'lms_quiz_questions';

    protected $fillable = [
        'quiz_id',
        'question_bank_id',
        'sort_order',
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
        return $this->belongsTo(LmsQuestionBanks::class, 'question_bank_id');
    }
}
