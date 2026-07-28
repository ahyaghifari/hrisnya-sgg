<?php

namespace App\Models\Shift;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperExtraHourAllowanceRules
 * @property string $id
 * @property string $company_id
 * @property string $allowanceable_type
 * @property string $allowanceable_id
 * @property numeric $amount_per_hour
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Model|\Eloquent $allowanceable
 * @property-read Companies $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules whereAllowanceableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules whereAllowanceableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules whereAmountPerHour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExtraHourAllowanceRules withoutTrashed()
 * @mixin \Eloquent
 */
class ExtraHourAllowanceRules extends Model
{
    use HasUlids, SoftDeletes;
    use BelongsToCompany;
    use LogsAuditChanges;

    protected $table = 'extra_hour_rules';

    protected $fillable = [
        'company_id',
        'allowanceable_type',
        'allowanceable_id',
        'amount_per_hour',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function allowanceable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function auditModule(): string
    {
        return 'payroll_mapping';
    }

    protected function auditCompanyId(): ?string
    {
        return $this->company_id;
    }

    protected function auditKey(): string
    {
        return 'extra_hour:' . $this->allowanceable_type . ':' . $this->allowanceable_id;
    }

    protected function auditLabel(): ?string
    {
        return 'Kelebihan Jam Kerja — ' . ($this->allowanceable?->name ?? '-');
    }

    protected function auditWatchedAttribute(): string
    {
        return 'amount_per_hour';
    }
}
