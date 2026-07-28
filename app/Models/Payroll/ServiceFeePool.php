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
 * @property string $id
 * @property string $company_id
 * @property string $payroll_period_id
 * @property numeric $total_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies $company
 * @property-read \App\Models\Payroll\PayrollPeriod $payrollPeriod
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceFeePool newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceFeePool newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceFeePool query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceFeePool whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceFeePool whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceFeePool whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceFeePool wherePayrollPeriodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceFeePool whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceFeePool whereUpdatedAt($value)
 * @mixin \Eloquent
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
