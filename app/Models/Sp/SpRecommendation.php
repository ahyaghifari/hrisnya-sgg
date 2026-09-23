<?php

namespace App\Models\Sp;

use App\Enum\Sp\SpLevel;
use App\Enum\Sp\SpRecommendationDecision;
use App\Enum\Sp\SpSourceType;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpRecommendation extends Model
{
    use HasUlids;

    protected $attributes = [
        'decision' => SpRecommendationDecision::PENDING->value,
    ];

    protected $fillable = [
        'user_id',
        'company_id',
        'sp_level',
        'reason_id',
        'source_type',
        'source_id',
        'reason_note',
        'decision',
        'reviewed_by',
        'reviewed_at',
        'employee_sp_id',
    ];

    protected $casts = [
        'sp_level' => SpLevel::class,
        'source_type' => SpSourceType::class,
        'decision' => SpRecommendationDecision::class,
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(SpReason::class, 'reason_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function employeeSp(): BelongsTo
    {
        return $this->belongsTo(EmployeeSp::class, 'employee_sp_id');
    }
}
