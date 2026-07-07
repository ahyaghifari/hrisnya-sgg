<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsUserHistories
 */
class LmsUserHistories extends Model
{
    use HasUlids;

    protected $table = 'lms_user_histories';

    protected $fillable = [
        'user_id',
        'course_id',
        'schedule_id',
        'score',
        'is_passed',
        'passed_at',
        'certificate_url',
        'archived_at',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function course()
    {
        return $this->belongsTo(\App\Models\LMS\LmsCourses::class, 'course_id');
    }

    public function schedule()
    {
        return $this->belongsTo(\App\Models\LMS\LmsCourseSchedules::class, 'schedule_id');
    }
}
