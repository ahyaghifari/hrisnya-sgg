<?php

namespace App\Models\LMS;

use App\Models\Master\Companies;
use App\Models\Master\Competencies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin IdeHelperLmsCourses
 * @property string $id
 * @property string|null $category_id
 * @property string|null $company_id
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property string $type_nakes
 * @property string $source_type
 * @property string|null $thumbnail
 * @property string $type
 * @property string|null $status
 * @property string|null $repeat_cycle Hanya untuk scheduler
 * @property int|null $repeat_interval Setiap X periode
 * @property string|null $first_start_date
 * @property string|null $last_end_date
 * @property string|null $next_run_date
 * @property string|null $start_date
 * @property string|null $end_date
 * @property string|null $due_date
 * @property int $minimum_score
 * @property int $requires_feedback
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\LMS\LmsCategories|null $category
 * @property-read Companies|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Competencies> $competencies
 * @property-read int|null $competencies_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\LmsCourseUsers> $courseUsers
 * @property-read int|null $course_users_count
 * @property-read \App\Models\User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\LmsCourseFeedbacks> $feedbacks
 * @property-read int|null $feedbacks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\LmsMeetings> $meetings
 * @property-read int|null $meetings_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereFirstStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereLastEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereMinimumScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereNextRunDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereRepeatCycle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereRepeatInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereRequiresFeedback($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereThumbnail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereTypeNakes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourses withoutTrashed()
 * @mixin \Eloquent
 */
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
