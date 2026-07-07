<?php

namespace App\Models\LMS;

use App\Models\Master\Competencies;
use App\Models\Master\Departments;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsCoursePlanItems
 */
class LmsCoursePlanItems extends Model
{
    use HasUlids;

    protected $table = 'lms_course_plan_items';

    protected $fillable = [
        'plan_id',
        'course_id',
        'source_type',
        'proposed_title',
        'proposed_description',
        'competency_id',
        'target_org_structure_id',
        'target_job_position_id',
        'estimated_participants',
        'estimated_cost',
        'request_status',
        'realized_course_id',
        'realized_schedule_id',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function plan()
    {
        return $this->belongsTo(LmsCoursePlans::class, 'plan_id');
    }

    public function course()
    {
        return $this->belongsTo(LmsCourses::class, 'course_id');
    }

    public function competency()
    {
        return $this->belongsTo(Competencies::class, 'competency_id');
    }

    public function targetDepartment()
    {
        return $this->belongsTo(OrgStructure::class, 'target_org_structure_id');
    }

    public function targetJobPosition()
    {
        return $this->belongsTo(JobPositions::class, 'target_job_position_id');
    }

    public function realizedCourse()
    {
        return $this->belongsTo(LmsCourses::class, 'realized_course_id');
    }

    public function realizedSchedule()
    {
        return $this->belongsTo(LmsCourseSchedules::class, 'realized_schedule_id');
    }

    public function schedules()
    {
        return $this->hasMany(LmsCoursePlanSchedules::class, 'plan_item_id');
    }

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (is_null($model->requested_at)) {
                $model->requested_at = now();
            }
        });
    }

    public function approvalSnapshot()
    {
        return $this->hasOne(\App\Models\ApprovalManagement\ApprovalSnapshots::class,'module_id', 'id')
            ->where('module_type', 'lms_course_request');
    }

    public function approvalHistories()
    {
        return $this->hasMany(\App\Models\ApprovalManagement\ApprovalHistories::class, 'requestable_id');
    }
}
