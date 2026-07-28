<?php

namespace App\Models\Assessment;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperAssessments
 * @property string $id
 * @property string $company_id
 * @property string $template_id
 * @property string|null $job_position_id
 * @property string|null $name
 * @property string $status
 * @property string|null $start_date
 * @property string|null $end_date
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies $company
 * @property-read \App\Models\Master\JobPositions|null $jobPosition
 * @property-read \App\Models\Assessment\AssessmentTemplates|null $template
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Assessment\AssessmentUsers> $userAssessments
 * @property-read int|null $user_assessments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments whereJobPositionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments whereTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessments withoutTrashed()
 * @mixin \Eloquent
 */
class Assessments extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'assessments';

    protected $fillable = [
        'name',
        'company_id',
        'template_id',
        'job_position_id',
        'status',
        'start_date',
        'end_date',
        'notes',
    ];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
    
    public function template()
    {
        return $this->belongsTo(\App\Models\Assessment\AssessmentTemplates::class, 'template_id');
    }

    public function jobPosition()
    {
        return $this->belongsTo(\App\Models\Master\JobPositions::class, 'job_position_id');
    }

    public function userAssessments()
    {
        return $this->hasMany(\App\Models\Assessment\AssessmentUsers::class, 'assessment_id');
    }
}
