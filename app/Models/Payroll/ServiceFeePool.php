<?php

namespace App\Models\Payroll;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pool "Total Jaspel" bulanan per company per periode — diisi manual finance sebelum
 * generate payroll company hospital.
 *
 * @mixin IdeHelperServiceFeePool
 */
class ServiceFeePool extends Model
{
    use HasUlids;

    protected $table = 'service_fee_pools';

    protected $fillable = [
        'company_id',
        'payroll_period_id',
        'total_amount',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }
}
