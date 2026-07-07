<?php

namespace App\Models\Payroll;

use App\Models\DoctorService\DoctorFeeItem;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperDoctorPayrollItem
 */
class DoctorPayrollItem extends Model
{
    use HasUlids;

    protected $table = 'doctor_payroll_items';

    protected $fillable = [
        'doctor_payroll_id',
        'doctor_fee_item_id',
        'service_name',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(DoctorPayroll::class, 'doctor_payroll_id');
    }

    public function doctorFeeItem(): BelongsTo
    {
        return $this->belongsTo(
            DoctorFeeItem::class,
            'doctor_fee_item_id'
        );
    }
}
