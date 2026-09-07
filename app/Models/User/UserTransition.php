<?php

namespace App\Models\User;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\UserTransitionTypeEnum;
use App\Models\ApprovalManagement\ApprovalHistories;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

class UserTransition extends Model
{
    use HasUlids;

    protected $fillable = [
        'company_id',
        'user_id',
        'number',
        'status',
        'transition_type',
        'transitionable_id',
        'transitionable_type',
        'role_id',
        'old_value',
        'effective_date',
        'contract_start_date',
        'contract_end_date',
        'reason',
        'file_path',
        'manager_comment',
        'created_by',
        'approved_by',
        'approved_at',
        'applied_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function transitionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Role::class, 'role_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function detail(): HasOne
    {
        return $this->hasOne(UserTransitionDetail::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(UserTransitionAttachment::class, 'user_transition_id');
    }

    public function approvalSnapshot(): HasOne
    {
        return $this->hasOne(ApprovalSnapshots::class,'module_id', 'id')
            ->where('module_type', ApprovalModuleEnum::USER_TRANSITION->value);
    }

    public function approvalHistories(): MorphMany
    {
        return $this->morphMany(ApprovalHistories::class, 'requestable');
    }

    public static function generateNumber(string $transitionType, $company): string
    {
        $companyCode = $company->code
            ?? strtoupper(substr($company->name, 0, 3));

        $type = UserTransitionTypeEnum::from($transitionType);
        $typeCode = $type->prefix();

        $today = now()->format('Ymd');

        $countToday = self::whereDate('created_at', now())
                ->count() + 1;

        $seq = str_pad($countToday, 3, '0', STR_PAD_LEFT);

        return "{$typeCode}/{$companyCode}/{$today}/{$seq}";
    }
}
