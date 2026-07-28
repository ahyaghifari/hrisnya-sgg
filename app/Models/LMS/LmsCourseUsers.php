<?php

namespace App\Models\LMS;

use App\Jobs\CreateMeetingUsersJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperLmsCourseUsers
 * @property string $id
 * @property string|null $user_id
 * @property string|null $course_id
 * @property int $is_mandatory
 * @property string|null $status
 * @property int $progress
 * @property int|null $score
 * @property int|null $ranking
 * @property string|null $enrolled_at
 * @property string|null $completed_at
 * @property int $is_passed
 * @property string|null $passed_at
 * @property string|null $certificate_url
 * @property string|null $archived_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LMS\LmsCourses|null $course
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\LmsCourseMeetingUsers> $meetingUsers
 * @property-read int|null $meeting_users_count
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereArchivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereCertificateUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereEnrolledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereIsMandatory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereIsPassed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers wherePassedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereProgress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereRanking($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseUsers whereUserId($value)
 * @mixin \Eloquent
 */
class LmsCourseUsers extends Model
{
    use HasUlids;

    protected $table = 'lms_course_users';

    protected $fillable = [
        'user_id',
        'course_id',
        'is_mandatory',
        'status',
        'progress',
        'score',
        'ranking',
        'enrolled_at',
        'completed_at',
        'is_passed',
        'passed_at',
        'certificate_url',
        'archived_at',
    ];

    protected static function booted(): void
    {
        static::created(function (LmsCourseUsers $courseUser): void {
            CreateMeetingUsersJob::dispatch($courseUser);
        });

        static::deleting(function (LmsCourseUsers $courseUser): void {
            $courseUser->meetingUsers()->delete();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(LmsCourses::class, 'course_id');
    }

    public function meetingUsers(): HasMany
    {
        return $this->hasMany(LmsCourseMeetingUsers::class, 'course_id', 'course_id')
            ->when(
                filled($this->user_id),
                fn ($query) => $query->where('user_id', $this->user_id)
            );
    }
}
