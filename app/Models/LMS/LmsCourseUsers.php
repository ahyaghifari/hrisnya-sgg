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
