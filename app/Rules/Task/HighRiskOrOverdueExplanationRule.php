<?php

namespace App\Rules\Task;

use App\Enum\OrgStructurLevel;
use App\Enum\Task\TaskRiskLevel;
use App\Models\Master\OrgStructure;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HighRiskOrOverdueExplanationRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $riskLevel = request()->input('risk_level');
        $completedAt = request()->input('completed_at');
        $dueDate = request()->input('due_date');

        $isHighRisk = $riskLevel === TaskRiskLevel::HIGH->value;
        $isLate = $completedAt && $dueDate && strtotime($completedAt) > strtotime($dueDate);

        if (($isHighRisk || $isLate) && empty($value)) {
            $fail(ucfirst(str_replace('_', ' ', $attribute)) . ' is required for high risk or overdue tasks.');
        }
    }
}
