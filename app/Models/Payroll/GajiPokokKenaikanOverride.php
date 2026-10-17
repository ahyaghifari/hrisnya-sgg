<?php

namespace App\Models\Payroll;

use App\Models\User;
use App\Models\Employee\Employee;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master override persen kenaikan Gaji Pokok per user (pengganti
 * App\Enum\Payroll\GajiPokokKenaikanOverrideEnum yang hardcode). Dicek
 * GajiPokokCalculator/GajiPokokBreakdownResolver, urutan prioritas: Gaji Khusus
 * (PayrollComponentOverride, skip Calculator total) -> baris aktif di sini -> Setting
 * `gaji_pokok_kenaikan_per_kelas` per company (default 8%).
 */
class GajiPokokKenaikanOverride extends Model
{
    use HasUlids;

    protected $table = 'gaji_pokok_kenaikan_overrides';

    protected $fillable = [
        'employee_id',
        'rate',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }


    /** Rate aktif (%) buat karyawan ini kalau ada, dipakai GajiPokokCalculator/BreakdownResolver. */
    public static function activeRateFor(?string $employeeId): ?float
    {
        if (! $employeeId) {
            return null;
        }

        $rate = static::query()->where('employee_id', $employeeId)->where('is_active', true)->value('rate');

        return $rate !== null ? (float) $rate : null;
    }
}
