<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin IdeHelperLmsQuestionBanks
 */
class LmsQuestionBanks extends Model
{
    use HasUlids;

    protected $table = 'lms_question_banks';

    protected $fillable = [
        'category_id',
        'question',
        'type',
        'max_score',
        'min_score',
        'created_by',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if ($model->created_by === null && Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        static::saving(function ($model) {
            if ($model->isDirty('type')) {
                if ($model->type->value === 'essay') {

                    if ($model->exists) {
                        $model->options()->delete();
                    } else {
                        $model->setRelation('options', collect());
                    }
                }

                if ($model->type->value === 'multiple_choice') {
                    $model->min_score = 0;
                    $model->max_score = 0;
                }
            }
        });

        static::saved(function ($model) {
            // Update total score for all quizzes that use this question
            if ($model->isDirty(['type', 'max_score'])) {
                $model->quizzes->each(function ($quiz) {
                    $quiz->updateTotalScore();
                });
            }
        });
    }

    public function options()
    {
        return $this->hasMany(LmsQuestionOptions::class, 'question_id', 'id');
    }

    public function quizzes()
    {
        return $this->belongsToMany(LmsQuizzes::class, 'lms_quiz_questions', 'question_bank_id', 'quiz_id')
            ->using(LmsQuizQuestions::class)
            ->withTimestamps();
    }

    public function category()
    {
        return $this->belongsTo(LmsCategories::class, 'category_id');
    }
}
