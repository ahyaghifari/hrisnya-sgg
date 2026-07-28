<?php

namespace App\Models\LMS\Trainer;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $assessment_id
 * @property string $variable_id
 * @property string $category_id
 * @property int $score
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LMS\Trainer\LmsMeetingHonorariumAssessment $assessment
 * @property-read \App\Models\LMS\Trainer\LmsHonorariumCategory $category
 * @property-read \App\Models\LMS\Trainer\LmsHonorariumVariable $variable
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumDetail whereAssessmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumDetail whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumDetail whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumDetail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingHonorariumDetail whereVariableId($value)
 * @mixin \Eloquent
 */
class LmsMeetingHonorariumDetail extends Model
{
    use HasUlids;

    protected $table = 'lms_meeting_honorarium_details';

    protected $fillable = [
        'assessment_id',
        'variable_id',
        'category_id',
        'score',
    ];

    protected static function booted(): void
    {
        static::saved(fn ($detail) => $detail->assessment?->recalculate());
        static::deleted(fn ($detail) => $detail->assessment?->recalculate());
    }

    public function assessment()
    {
        return $this->belongsTo(LmsMeetingHonorariumAssessment::class, 'assessment_id', 'id');
    }

    public function variable()
    {
        return $this->belongsTo(LmsHonorariumVariable::class, 'variable_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(LmsHonorariumCategory::class, 'category_id', 'id');
    }
}
