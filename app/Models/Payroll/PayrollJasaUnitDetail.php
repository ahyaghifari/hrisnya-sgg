<?php

namespace App\Models\Payroll;

use App\Casts\PayrollEncrypted;
use App\Models\Employee\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Input nominal Jasa Unit per karyawan per periode — 100% manual, dibaca JasaUnitCalculator
 * saat generate payroll. Pola sama persis PayrollManualLine, cuma jasanya bertipe (JasaUnitType)
 * bukan komponen tetap. Pengganti tabel jasa_unit_details (dikunci user_id): data disalin saat
 * migration create_payroll_jasa_unit_details_table, tabel lama dibiarkan beku. created_by tetap
 * menunjuk users (aktor).
 */
class PayrollJasaUnitDetail extends Model
{
    use HasUlids;

    protected $table = 'payroll_jasa_unit_details';

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
