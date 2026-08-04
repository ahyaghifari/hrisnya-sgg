<?php

namespace App\Models\LMS;

use App\Models\Master\Companies;
use App\Models\Master\Competencies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LmsCourses extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'lms_courses';

    protected $fillable = [
        'category_id',
        'company_id',
        'code',
        'title',
        'description',
        'type_nakes',
        'source_type',
        'thumbnail',
        'type',
        'status',
        'repeat_cycle',
        'repeat_interval',
        'first_start_date',
        'last_end_date',
        'next_run_date',
        'start_date',
        'end_date',
        'due_date',
        'minimum_score',
        'requires_feedback',
        'feedback_public_token',
        'competency_id',
        'start_time',
        'end_time',
        'created_by',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if ($model->created_by === null && Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        static::saving(function ($model) {
            $model->syncDatesFromMeetings();
        });
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(LmsCategories::class, 'category_id', 'id');
    }

    public function meetings()
    {
        return $this->hasMany(LmsMeetings::class, 'course_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function courseUsers()
    {
        return $this->hasMany(LmsCourseUsers::class, 'course_id', 'id');
    }

    public function isOverdue(): bool
    {
        return $this->due_date && \Carbon\Carbon::parse($this->due_date)->isPast();
    }

    public function syncDatesFromMeetings()
    {
        $meetings = $this->meetings()->get(['start_date', 'end_date']);
        if ($meetings->count() > 0) {
            $this->start_date = $meetings->min('start_date');
            $this->end_date = $meetings->max('end_date');
        } else {
            $this->start_date = null;
            $this->end_date = null;
        }
    }

    public function competencies()
    {
        return $this->belongsToMany(Competencies::class, 'lms_course_competencies', 'lms_course_id', 'competency_id')->withTimestamps();
    }

    public function feedbacks()
    {
        return $this->hasMany(LmsCourseFeedbacks::class, 'course_id', 'id');
    }

    protected static function booted()
    {
        static::saving(function ($model) {
            if ($model->type === 'seasonal') {
                $model->repeat_cycle = null;
                $model->repeat_interval = null;
                $model->first_start_date = null;
                $model->last_end_date = null;
                $model->next_run_date = null;
            }
        });
    }
}
