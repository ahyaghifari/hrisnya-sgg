<?php

namespace App\Models\LMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class LmsCoursePlans extends Model
{
    use HasUlids;

    protected $table = 'lms_course_plans';

    protected $fillable = [
        'title',
        'year',
        'type_nakes',
        'status',
        'total_courses',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    public function items()
    {
        return $this->hasMany(LmsCoursePlanItems::class, 'plan_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
