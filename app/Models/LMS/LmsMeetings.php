<?php

namespace App\Models\LMS;

use App\Models\LMS\Trainer\Trainers;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsMeetings
 */
class LmsMeetings extends Model
{
    use HasUlids;

    protected $table = 'lms_meetings';

    protected $fillable = [
        'course_id',
        'type_trainer',
        'trainer_id',
        'trainer_employee_id',
        'title',
        'description',
        'meeting_date',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'attendance_type',
    ];
    
    protected $casts = [
        'description' => 'string',
    ];

    public function course()
    {
        return $this->belongsTo(LmsCourses::class, 'course_id', 'id');
    }

    public function materials()
    {
        return $this->hasMany(LmsMaterials::class, 'meeting_id', 'id');
    }
    
    public function quizzes()
    {
        return $this->hasMany(LmsMeetingQuizzes::class, 'meeting_id', 'id');
    }

    public function attendance()
    {
        return $this->hasOne(LmsMeetingAttendances::class, 'meeting_id', 'id');
    }

    public function meetingUsers()
    {
        return $this->hasMany(LmsCourseMeetingUsers::class, 'meeting_id', 'id');
    }

    public function trainerEmployee()
    {
        return $this->belongsTo(User::class, 'trainer_employee_id', 'id');
    }

    public function trainer()
    {
        return $this->belongsTo(Trainers::class, 'trainer_id', 'id');
    }

    public function honorariumAssessment()
    {
        return $this->hasOne(\App\Models\LMS\Trainer\LmsMeetingHonorariumAssessment::class, 'meeting_id', 'id');
    }

    protected static function booted()
    {
        static::saving(function ($meeting) {
            if (is_null($meeting->sort_order)) {
            $meeting->sort_order = static::max('sort_order') + 1;
            }
        });

        static::saved(function ($meeting) {
            $course = $meeting->course;
            if ($course) {
                $dates = $course->meetings()->get(['start_date', 'end_date']);
                $course->start_date = $dates->min('start_date');
                $course->end_date = $dates->max('end_date');
                $course->save();
            }
        });

        static::deleted(function ($meeting) {
            $course = $meeting->course;
            if ($course) {
                $dates = $course->meetings()->get(['start_date', 'end_date']);
                $course->start_date = $dates->min('start_date');
                $course->end_date = $dates->max('end_date');
                $course->save();
            }
        });
    }
}
