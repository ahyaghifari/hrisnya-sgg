<?php

namespace App\Models\Assessment;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperAssessmentTemplates
 * @property string $id
 * @property string $company_id
 * @property string $type_id
 * @property string $name
 * @property string|null $description
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Assessment\AssessmentTypes|null $assessmentType
 * @property-read \App\Models\Master\Companies $company
 * @property-read \App\Models\Master\JobPositions|null $jobPosition
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Assessment\AssessmentTemplateItems> $templateItems
 * @property-read int|null $template_items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates whereTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplates withoutTrashed()
 * @mixin \Eloquent
 */
class AssessmentTemplates extends Model
{
    use HasUlids, SoftDeletes;
    use BelongsToCompany;

    protected $table = 'assessment_templates';

    protected $fillable = [
        'company_id',
        'type_id',
        'name',
        'description',
        'is_active',
    ];

    public function assessmentType()
    {
        return $this->belongsTo(AssessmentTypes::class, 'type_id');
    }

    public function jobPosition()
    {
        return $this->belongsTo(\App\Models\Master\JobPositions::class, 'job_position_id');
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Master\Companies::class, 'company_id');
    }

    public function templateItems()
    {
        return $this->hasMany(AssessmentTemplateItems::class, 'template_id');
    }
}
