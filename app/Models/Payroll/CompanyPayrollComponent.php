<?php

namespace App\Models\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Registry payroll — saklar ON/OFF per company untuk tiap PayrollComponentEnum. Baris
 * cuma dibuat kalau admin pernah nge-toggle (auto-create saat pertama diaktifkan/nonaktifkan),
 * bukan di-seed penuh 20 baris x company dari awal.
 *
 * @mixin IdeHelperCompanyPayrollComponent
 */
class CompanyPayrollComponent extends Model
{
    use HasUlids;

    protected $table = 'company_payroll_components';

    protected $fillable = [
        'company_id',
        'component',
        'is_active',
        'eligible_employee_statuses',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'eligible_employee_statuses' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function componentEnum(): PayrollComponentEnum
    {
        return PayrollComponentEnum::from($this->component);
    }

    /**
     * eligible_employee_statuses menyimpan ID m_employee_types (bukan enum literal — cleanup
     * "status karyawan jadi enum" sengaja belum dieksekusi, lihat analisis-payroll-5 §7.3).
     * Kosong/null = berlaku semua tipe karyawan.
     */
    public function isEligibleForEmployeeType(?string $employeeTypeId): bool
    {
        $required = Collection::make($this->eligible_employee_statuses ?? []);

        if ($required->isEmpty()) {
            return true;
        }

        return $required->contains($employeeTypeId);
    }
}
