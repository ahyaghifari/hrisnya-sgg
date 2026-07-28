<?php

namespace App\Models\LMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsCourseMeetingUsers
 * @property string $id
 * @property string|null $user_id
 * @property string|null $course_id
 * @property string|null $meeting_id
 * @property int $is_attended
 * @property int|null $is_present
 * @property int $quiz_completed
 * @property int|null $quiz_score
 * @property int $is_completed
 * @property string|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LMS\LmsCourses|null $course
 * @property-read \App\Models\LMS\LmsMeetings|null $meeting
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers whereIsAttended($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers whereIsCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers whereIsPresent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers whereMeetingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers whereQuizCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers whereQuizScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseMeetingUsers whereUserId($value)
 * @mixin \Eloquent
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
        'absen_masuk',
        'absen_pulang',
        'keterangan_kehadiran',
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
