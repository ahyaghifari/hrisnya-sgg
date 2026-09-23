<?php

namespace App\Models\MeritPoint;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ledger - tidak pernah diedit (sengaja tanpa updated_at, lihat migration). */
class MeritPointTransaction extends Model
{
    use HasUlids;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'rule_id',
        'points',
        'reason',
        'source_type',
        'source_id',
        'created_by',
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(MeritPointRule::class, 'rule_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
