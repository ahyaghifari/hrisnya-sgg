<?php

namespace App\Models\Employee;

use App\Casts\PayrollEncrypted;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Roster pendapatan/potongan manual khusus untuk User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS - lihat
 * ExecutiveAllowanceCalculator. Pengganti tabel executive_allowances (dikunci user_id): data disalin
 * saat migration create_employee_executive_allowances_table, tabel lama dibiarkan beku.
 */
class EmployeeExecutiveAllowance extends Model
{
    use HasUlids;

    protected $table = 'employee_executive_allowances';

    protected $fillable = [
        'employee_id',
        'component',
        'type',
        'label',
        'amount',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'amount' => PayrollEncrypted::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
