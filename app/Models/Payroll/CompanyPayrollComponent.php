<?php

namespace App\Models\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function componentEnum(): PayrollComponentEnum
    {
        return PayrollComponentEnum::from($this->component);
    }
}
