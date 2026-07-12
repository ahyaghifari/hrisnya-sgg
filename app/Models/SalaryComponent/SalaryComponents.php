<?php

namespace App\Models\SalaryComponent;

use App\Enum\Salary\SalaryComponentCategoryEnum;
use App\Models\Concerns\BelongsToCompanyOrShared;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Payroll\SalaryComponentAssignments;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Katalog master komponen gaji (earning/deduction). Beda dari SalaryComponentAssignments,
 * yang mencatat SIAPA dapat komponen apa berapa besarannya.
 *
 * @mixin IdeHelperSalaryComponents
 */
class SalaryComponents extends Model
{
    use HasUlids;
    use BelongsToCompanyOrShared;

    protected $table = 'salary_components';

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'category',
        'is_active',
        'calculation_type',
        'formula_key',
        'default_value',
        'note',
    ];

    public function assignments()
    {
        return $this->hasMany(SalaryComponentAssignments::class, 'salary_component_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class);
    }

    /**
     * Tipe karyawan yang eligible untuk komponen ini. Kosong (tidak ada baris pivot) = berlaku
     * semua tipe karyawan (default). Berlaku sama ke semua assignment komponen ini.
     */
    public function employeeTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            EmployeeTypes::class,
            'salary_component_employee_types',
            'salary_component_id',
            'employee_type_id'
        );
    }

    protected static function booted(): void
    {
        static::saving(function (self $component) {
            if ($component->category) {
                $component->type = SalaryComponentCategoryEnum::from($component->category)->type()->value;
            }
        });
    }
}
