<?php

namespace App\Models\LMS;

use App\Enum\QuestionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Question\Question;

/**
 * @mixin IdeHelperLmsQuizzes
 */
class LmsQuizzes extends Model
{
    use HasUlids;

    protected $table = 'lms_quizzes';

    protected $fillable = [
        'category_id',
        'title',
        'total_score',
        'created_by',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            $model->updateTotalScore();
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category()
    {
        return $this->belongsTo(LmsCategories::class, 'category_id');
    }

    public function questions()
    {
        return $this->belongsToMany(LmsQuestionBanks::class, 'lms_quiz_questions', 'quiz_id', 'question_bank_id')
            ->using(LmsQuizQuestions::class)
            ->withTimestamps();
    }

    public function pivotTable()
    {
        return $this->hasMany(LmsQuizQuestions::class, 'quiz_id', 'id');
    }

    /**
     * Calculate and update total score based on questions
     */
    public function updateTotalScore()
    {
        $totalScore = $this->calculateTotalScore();
        
        // Update without triggering events to avoid infinite loop
        $this->newQuery()->where('id', $this->id)->update(['total_score' => $totalScore]);
        $this->total_score = $totalScore;
    }

    /**
     * Calculate total score based on question types
     */
    public function calculateTotalScore()
    {
        $questions = $this->questions()->get();
        
        $multipleChoiceCount = 0;
        $essayTotalScore = 0;
        
        foreach ($questions as $question) {
            if ($question->type === QuestionType::MultipleChoice->value) {
                $multipleChoiceCount++;
            } elseif ($question->type === QuestionType::Essay->value) {
                $essayTotalScore += $question->max_score ?? 0;
            }
        }

        // Multiple choice: each question worth 100 points
        $multipleChoiceScore = $multipleChoiceCount * 100;
        
        // Total score is sum of multiple choice and essay scores
        return $multipleChoiceScore + $essayTotalScore;
    }
}
