<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsCourseFeedbackAnswer
 * @property string $id
 * @property string $course_feedback_id
 * @property string $question_id
 * @property int $score Skala tetap 1 (Kurang Baik) - 5 (Sangat Baik)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LMS\LmsCourseFeedbacks $courseFeedback
 * @property-read \App\Models\LMS\LmsFeedbackQuestion $question
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbackAnswer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbackAnswer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbackAnswer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbackAnswer whereCourseFeedbackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbackAnswer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbackAnswer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbackAnswer whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbackAnswer whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbackAnswer whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LmsCourseFeedbackAnswer extends Model
{
    use HasUlids;

    protected $table = 'lms_course_feedback_answers';

    protected $fillable = [
        'course_feedback_id',
        'question_id',
        'score',
    ];

    public function courseFeedback()
    {
        return $this->belongsTo(LmsCourseFeedbacks::class, 'course_feedback_id');
    }

    public function question()
    {
        return $this->belongsTo(LmsFeedbackQuestion::class, 'question_id');
    }
}
