<?php

namespace App\Models\LMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsCourseMeetingUsers
 */
class LmsCourseMeetingUsers extends Model
{
    use HasUlids;

    protected $table = 'lms_course_meeting_users';

    protected $fillable = [
        'user_id',
        'course_id',
        'meeting_id',
        'is_attended',
        'is_present',
        'quiz_completed',
        'quiz_score',
        'is_completed',
        'completed_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(LmsCourses::class, 'course_id');
    }

    public function meeting()
    {
        return $this->belongsTo(LmsMeetings::class, 'meeting_id');
    }
}
