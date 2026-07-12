<?php

namespace App\Models\Payroll;

use App\Enum\Salary\CalculationTypeEnum;
use App\Models\SalaryComponent\SalaryComponents;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperSalaryComponentAssignments
 */
class SalaryComponentAssignments extends Model
{
    use HasUlids;

    protected $table = 'salary_component_assignments';

    protected $fillable = [
        'salaryable_type',
        'salaryable_id',
        'salary_component_id',
        'amount',
        'percentage',
        'is_overrideable',
    ];

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponents::class, 'salary_component_id');
    }

    public function salaryable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeIsFixed($query, $isFixed = true)
    {
        return $query->whereHas('salaryComponent', fn($q) => $q->where('calculation_type', $isFixed ? CalculationTypeEnum::FIXED->value : CalculationTypeEnum::PERCENTAGE->value)
        );
    }
}
