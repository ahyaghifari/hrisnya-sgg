<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsCourseSchedules
 */
class LmsCourseSchedules extends Model
{
    use HasUlids;

    protected $fillable = [
        'course_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'status',
    ];

    public function course()
    {
        return $this->belongsTo(LmsCourses::class, 'course_id');
    }
}
