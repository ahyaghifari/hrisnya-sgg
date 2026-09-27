<?php

namespace App\Models\MeritPoint;

use App\Enum\MeritPoint\MeritPointTriggerType;
use App\Enum\MeritPoint\MeritPointType;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeritPointRule extends Model
{
    use HasUlids;

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'points',
        'bonus_points',
        'trigger_type',
        'event',
        'is_active',
    ];

    protected $casts = [
        'type' => MeritPointType::class,
        'points' => 'integer',
        'bonus_points' => 'integer',
        'trigger_type' => MeritPointTriggerType::class,
        'event' => \App\Enum\MeritPoint\MeritPointEvent::class,
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
