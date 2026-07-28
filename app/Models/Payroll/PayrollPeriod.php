<?php

namespace App\Models\Payroll;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperPayrollPeriod
 * @property string $id
 * @property string|null $company_id
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property \Illuminate\Support\Carbon|null $payment_date
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property string|null $generated_by
 * @property string|null $signer_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies|null $company
 * @property-read User|null $generatedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payroll\Payroll> $payrolls
 * @property-read int|null $payrolls_count
 * @property-read User|null $signer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollPeriod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollPeriod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollPeriod query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollPeriod whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollPeriod whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollPeriod whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollPeriod whereGeneratedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollPeriod whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollPeriod wherePaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollPeriod whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollPeriod whereSignerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollPeriod whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PayrollPeriod whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PayrollPeriod extends Model
{
    use HasUlids;
    use BelongsToCompany;

    protected $table = 'payroll_periods';

    protected $fillable = [
        'company_id',
        'start_date',
        'end_date',
        'payment_date',
        'processed_at',
        'generated_by',
        'signer_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'payment_date' => 'date',
        'processed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }
}
