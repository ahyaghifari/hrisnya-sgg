<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsCourseFeedbacks
 * @property string $id
 * @property string $course_id
 * @property string $user_id
 * @property int|null $rating
 * @property string|null $feedback
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\LmsCourseFeedbackAnswer> $answers
 * @property-read int|null $answers_count
 * @property-read \App\Models\LMS\LmsCourses|null $course
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbacks newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbacks newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbacks query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbacks whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbacks whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbacks whereFeedback($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbacks whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbacks whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbacks whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseFeedbacks whereUserId($value)
 * @mixin \Eloquent
 */
class LmsCourseFeedbacks extends Model
{
    use HasUlids;

    protected $table = 'lms_course_feedbacks';

    protected $fillable = [
        'course_id',
        'user_id',
        'rating',
        'feedback',
    ];

    public function course()
    {
        return $this->belongsTo(LmsCourses::class, 'course_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function answers()
    {
        return $this->hasMany(LmsCourseFeedbackAnswer::class, 'course_feedback_id');
    }
}
