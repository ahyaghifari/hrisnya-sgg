<?php

namespace App\Models\LMS;

use App\Models\Master\Competencies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsCourseCompetencies
 * @property string $lms_course_id
 * @property string $competency_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Competencies $competency
 * @property-read \App\Models\LMS\LmsCourses|null $course
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseCompetencies newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseCompetencies newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseCompetencies query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseCompetencies whereCompetencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseCompetencies whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseCompetencies whereLmsCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseCompetencies whereUpdatedAt($value)
 * @mixin \Eloquent
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
