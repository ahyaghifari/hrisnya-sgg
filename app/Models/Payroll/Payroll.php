<?php

namespace App\Models\Payroll;

use App\Enum\Salary\SalaryComponentTypeEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperPayroll
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
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function earningItems(): HasMany
    {
        return $this->items()->where('type', SalaryComponentTypeEnum::EARNING->value);
    }

    public function deductionItems(): HasMany
    {
        return $this->items()->where('type', SalaryComponentTypeEnum::DEDUCTION->value);
    }
}
