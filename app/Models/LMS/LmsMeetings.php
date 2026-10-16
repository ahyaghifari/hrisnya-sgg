<?php

namespace App\Models\LMS;

use App\Enum\LMS\LmsSettingEnum;
use App\Models\LMS\Trainer\Trainers;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        'jp',
        'attendance_type',
        'attendance_method',
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

    /**
     * 1 JP = sekian menit (setting `menit_1_jp`, default 45) — durasi start_time-end_time
     * dibagi angka itu lalu dibulatkan. Dihitung ulang tiap save supaya kalau setting-nya
     * berubah, JP pertemuan lama tidak ikut ter-update sendiri (snapshot saat disimpan).
     */
    public function calculateJp(): ?int
    {
        if (! $this->start_time || ! $this->end_time) {
            return null;
        }

        $minutes = Carbon::parse($this->start_time)->diffInMinutes(Carbon::parse($this->end_time));
        $minutesPerJp = Setting::resolve(LmsSettingEnum::MENIT_1_JP->value, null, (int) LmsSettingEnum::MENIT_1_JP->default());

        return $minutesPerJp > 0 ? (int) round($minutes / $minutesPerJp) : null;
    }

    protected static function booted()
    {
        static::saving(function ($meeting) {
            if (is_null($meeting->sort_order)) {
            $meeting->sort_order = static::max('sort_order') + 1;
            }

            $meeting->jp = $meeting->calculateJp();
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
