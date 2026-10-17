<?php

namespace App\Models\Employee;

use App\Casts\PayrollEncrypted;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keanggotaan koperasi karyawan + cicilan bulanan tetapnya (1 baris per karyawan). Pengganti
 * user_koperasi (dikunci user_id): data disalin saat migration create_employee_koperasi_table,
 * tabel lama dibiarkan beku. Dibaca KoperasiCalculator tiap Generate/Regenerate.
 */
class EmployeeKoperasi extends Model
{
    use HasUlids;

    protected $table = 'employee_koperasi';

    protected $fillable = [
        'employee_id',
        'amount',
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
