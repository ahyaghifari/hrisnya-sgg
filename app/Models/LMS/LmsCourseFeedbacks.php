<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsCourseFeedbacks
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
}
