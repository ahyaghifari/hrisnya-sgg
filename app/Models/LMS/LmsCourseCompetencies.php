<?php

namespace App\Models\LMS;

use App\Models\Master\Competencies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsCourseCompetencies
 */
class LmsCourseCompetencies extends Model
{
    use HasUlids;

    protected $fillable = [
        'lms_course_id',
        'competency_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }
        });
    } 

    public function course()
    {
        return $this->belongsTo(LmsCourses::class, 'lms_course_id');
    }

    public function competency()
    {
        return $this->belongsTo(Competencies::class, 'competency_id');
    }
}
