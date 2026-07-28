<?php

namespace App\Models\Payroll;

use App\Enum\Payroll\PayrollComponentTypeEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperPayroll
 * @property string $id
 * @property string $user_id
 * @property string $payroll_period_id
 * @property numeric $gross_salary
 * @property numeric $total_deductions
 * @property numeric $net_salary
 * @property int $is_paid
 * @property \Illuminate\Support\Carbon|null $slip_sent_at
 * @property string|null $generated_by
 * @property \Illuminate\Support\Carbon|null $generated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payroll\PayrollItem> $deductionItems
 * @property-read int|null $deduction_items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payroll\PayrollItem> $earningItems
 * @property-read int|null $earning_items_count
 * @property-read User|null $generatedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payroll\PayrollItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\Payroll\PayrollPeriod $payrollPeriod
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll whereGeneratedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll whereGeneratedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll whereGrossSalary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll whereIsPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll whereNetSalary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll wherePayrollPeriodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll whereSlipSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll whereTotalDeductions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payroll whereUserId($value)
 * @mixin \Eloquent
 */
class Payroll extends Model
{
    use HasUlids;

    protected $table = 'payrolls';

    protected $fillable = [
        'user_id',
        'payroll_period_id',
        'gross_salary',
        'total_deductions',
        'net_salary',
        'is_paid',
        'slip_sent_at',
        'generated_by',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'slip_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function earningItems(): HasMany
    {
        return $this->items()->where('type', PayrollComponentTypeEnum::EARNING->value);
    }

    public function deductionItems(): HasMany
    {
        return $this->items()->where('type', PayrollComponentTypeEnum::DEDUCTION->value);
    }
}
