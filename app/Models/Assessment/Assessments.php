<?php

namespace App\Models\Assessment;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
