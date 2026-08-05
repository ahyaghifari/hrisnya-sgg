<?php

namespace App\Models\LMS;

use App\Models\LMS\Trainer\LmsHonorariumCategory;
use App\Models\LMS\Trainer\LmsHonorariumVariable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class TrainingRequestHonorariumDetail extends Model
{
    use HasUlids;

    protected $table = 'training_request_honorarium_details';

    protected $fillable = [
        'training_request_id',
        'variable_id',
        'category_id',
        'score',
    ];

    protected static function booted(): void
    {
        static::saved(fn ($detail) => $detail->trainingRequest?->recalculateIkatanDinas());
        static::deleted(fn ($detail) => $detail->trainingRequest?->recalculateIkatanDinas());
    }

    public function trainingRequest()
    {
        return $this->belongsTo(TrainingRequests::class, 'training_request_id', 'id');
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
