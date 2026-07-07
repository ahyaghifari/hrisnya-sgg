<?php

namespace App\Models\Payroll;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperDoctorPayrollPeriod
 */
class DoctorPayrollPeriod extends Model
{
    use HasUlids;

    protected $table = 'doctor_payroll_periods';

    protected $fillable = [
        'company_id',
        'start_date',
        'end_date',
        'processed_at',
    ];


    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(DoctorPayroll::class, 'doctor_payroll_period_id');
    }
}
