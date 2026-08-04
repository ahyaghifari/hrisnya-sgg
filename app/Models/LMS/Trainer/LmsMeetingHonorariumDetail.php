<?php

namespace App\Models\LMS\Trainer;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
