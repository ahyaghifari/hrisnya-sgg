<?php

namespace App\Models\Payroll;

use App\Casts\PayrollEncrypted;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Input nominal Jasa Unit per karyawan per periode — 100% manual, dibaca JasaUnitCalculator
 * saat generate payroll. Pola sama persis PayrollManualLine, cuma jasanya bertipe (JasaUnitType)
 * bukan komponen tetap.
 */
class JasaUnitDetail extends Model
{
    use HasUlids;

    protected $table = 'jasa_unit_details';

    protected $fillable = [
        'payroll_period_id',
        'user_id',
        'jasa_unit_type_id',
        'amount',
        'note',
        'created_by',
    ];

    protected $casts = [
        'amount' => PayrollEncrypted::class,
    ];

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function jasaUnitType(): BelongsTo
    {
        return $this->belongsTo(JasaUnitType::class, 'jasa_unit_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
