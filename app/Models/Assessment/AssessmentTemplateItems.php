<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AssessmentTemplateItems extends Model
{
    use HasUlids;

    protected $table = 'assessment_template_items';

    protected $fillable = [
        'template_id',
        'sort_order',
        'name',
        'type',
        'group_label',
        'weight_percent',
        'target_score',
        'max_score',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(AssessmentTemplates::class, 'template_id');
    }
}
