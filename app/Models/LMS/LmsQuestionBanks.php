<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin IdeHelperLmsQuestionBanks
 * @property string $id
 * @property string|null $category_id
 * @property string $question
 * @property string $type
 * @property int $max_score
 * @property int $min_score
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\LmsQuizAnswers> $answers
 * @property-read int|null $answers_count
 * @property-read \App\Models\LMS\LmsCategories|null $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\LmsQuestionOptions> $options
 * @property-read int|null $options_count
 * @property-read \App\Models\LMS\LmsQuizQuestions|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\LmsQuizzes> $quizzes
 * @property-read int|null $quizzes_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionBanks newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionBanks newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionBanks query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionBanks whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionBanks whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionBanks whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionBanks whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionBanks whereMaxScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionBanks whereMinScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionBanks whereQuestion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionBanks whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionBanks whereUpdatedAt($value)
 * @mixin \Eloquent
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

    public function answers()
    {
        return $this->hasMany(LmsQuizAnswers::class, 'question_id');
    }
}
