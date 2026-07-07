<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperDoctorPayroll
 */
class DoctorPayroll extends Model
{
    use HasUlids;

    protected $table = 'doctor_payrolls';

    protected $fillable = [
        'doctor_id',
        'doctor_payroll_period_id',
        'gross_salary',
        'tax_percentage',
        'tax_amount',
        'net_salary',
        'is_paid',
        'paid_at',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(
            DoctorPayrollPeriod::class,
            'doctor_payroll_period_id'
        );
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'doctor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DoctorPayrollItem::class, 'doctor_payroll_id');
    }
}
