<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAssessmentTemplateItems
 * @property string $id
 * @property string $template_id
 * @property int $sort_order
 * @property string $name
 * @property string|null $type
 * @property string|null $group_label
 * @property numeric|null $weight_percent
 * @property numeric|null $target_score
 * @property numeric $max_score
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Assessment\AssessmentTemplates|null $template
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems whereGroupLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems whereMaxScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems whereTargetScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems whereTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentTemplateItems whereWeightPercent($value)
 * @mixin \Eloquent
 */
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
