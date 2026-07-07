<?php

namespace App\Models\Payroll;

use App\Models\Concerns\BelongsToCompanyOrShared;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperMasterSalaryComponents
 */
class MasterSalaryComponents extends Model
{
    use HasUlids;
    use BelongsToCompanyOrShared;

    protected $table = 'm_salary_components';

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'is_active',
        'calculation_type',
        'formula_key',
        'default_value',
        'note',
    ];

    public function jobPositionComponents()
    {
        return $this->hasMany(SalaryComponents::class, 'salary_component_id');
    }
}
