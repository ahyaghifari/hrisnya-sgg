<?php

namespace App\Models\Payroll;

use App\Casts\PayrollEncrypted;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\User;
use App\Models\Employee\Employee;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override manual per (user, component) - lihat migration create_payroll_component_overrides_table
 * untuk kenapa ini beda dari applyManualRevision()/edited_manually (standing policy vs koreksi
 * 1 payroll). Dicek PayrollService::runComponent() tiap kali Generate/Regenerate.
 */
class PayrollComponentOverride extends Model
{
    use HasUlids;

    protected $table = 'payroll_component_overrides';

    protected $fillable = [
        'employee_id',
        'component',
        'amount',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'component' => PayrollComponentEnum::class,
            'is_active' => 'boolean',
            'amount' => PayrollEncrypted::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }


    /** Override aktif buat (karyawan, component) ini, kalau ada - dipakai PayrollService::runComponent(). */
    public static function activeFor(string $employeeId, string $component): ?self
    {
        return static::query()
            ->where('employee_id', $employeeId)
            ->where('component', $component)
            ->where('is_active', true)
            ->first();
    }
}
