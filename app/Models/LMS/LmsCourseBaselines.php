<?php

namespace App\Models\LMS;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperLmsCourseBaselines
 * @property string $id
 * @property string $company_id
 * @property string $start_date
 * @property string|null $end_date
 * @property int $min_courses
 * @property int $min_hours
 * @property int $is_active
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Companies $company
 * @property-read User|null $creator
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines whereMinCourses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines whereMinHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsCourseBaselines withoutTrashed()
 * @mixin \Eloquent
 */
class LmsCourseBaselines extends Model
{
    use HasUlids, SoftDeletes;
    use BelongsToCompany;

    protected $table = 'lms_course_baselines';

    protected $fillable = [
        'company_id',
        'start_date',
        'end_date',
        'min_courses',
        'min_hours',
        'is_active',
        'created_by',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
