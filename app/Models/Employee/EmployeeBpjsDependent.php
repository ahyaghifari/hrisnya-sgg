<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tanggungan BPJS Kesehatan per karyawan — 1 baris per karyawan (unique). `count` yang dipakai
 * BpjsKesehatanCalculator, `note` cuma keterangan bebas (mis. "1 ibu, 1 adik"). Pengganti tabel
 * bpjs_dependents (dikunci user_id): data disalin saat migration create_employee_bpjs_dependents_table,
 * tabel lama dibiarkan beku.
 */
class EmployeeBpjsDependent extends Model
{
    use HasUlids;

    protected $table = 'employee_bpjs_dependents';

    protected $fillable = [
        'employee_id',
        'count',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
