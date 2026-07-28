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

/**
 * @mixin IdeHelperUserTransition
 * @property string $id
 * @property string $number
 * @property string $status
 * @property string $company_id
 * @property string $user_id
 * @property string $transition_type
 * @property string|null $transitionable_id
 * @property string|null $transitionable_type
 * @property int|null $role_id
 * @property string|null $old_value
 * @property string|null $effective_date
 * @property string|null $reason
 * @property string|null $manager_comment
 * @property string $created_by
 * @property string|null $approved_by
 * @property string|null $approved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ApprovalHistories> $approvalHistories
 * @property-read int|null $approval_histories_count
 * @property-read ApprovalSnapshots|null $approvalSnapshot
 * @property-read User|null $approvedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User\UserTransitionAttachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read Companies $company
 * @property-read User|null $createdBy
 * @property-read \App\Models\User\UserTransitionDetail|null $detail
 * @property-read \App\Models\Role|null $role
 * @property-read Model|\Eloquent|null $transitionable
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereEffectiveDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereManagerComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereOldValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereTransitionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereTransitionableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereTransitionableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTransition whereUserId($value)
 * @mixin \Eloquent
 */
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
        'reason',
        'file_path',
        'manager_comment',
        'created_by',
        'approved_by',
        'approved_at'
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
