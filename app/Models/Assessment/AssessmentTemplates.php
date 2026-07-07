<?php

namespace App\Models\Assessment;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperAssessmentTemplates
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
