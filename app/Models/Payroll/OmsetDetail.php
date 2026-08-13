<?php

namespace App\Models\Payroll;

use App\Casts\PayrollEncrypted;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 baris = nominal 1 kategori omset di 1 periode gaji. Dijumlah (SUM amount per
 * payroll_period_id) jadi Total Omset dipakai JasaPelayananCalculator.
 */
class OmsetDetail extends Model
{
    use HasUlids;

    protected $table = 'omset_details';

    protected $fillable = [
        'payroll_period_id',
        'omset_category_id',
        'amount',
    ];

    protected $casts = [
        'amount' => PayrollEncrypted::class,
    ];

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(OmsetCategory::class, 'omset_category_id');
    }
}
