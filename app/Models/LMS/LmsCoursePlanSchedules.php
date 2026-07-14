<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class LmsCoursePlanSchedules extends Model
{
    use HasUlids;

    protected $table = 'lms_course_plan_schedules';

    protected $fillable = [
        'plan_item_id',
        'planned_month',
        'planned_week',
        'planned_date',
        'status',
        'notes',
    ];

    public function planItem()
    {
        return $this->belongsTo(LmsCoursePlanItems::class, 'plan_item_id');
    }
}
