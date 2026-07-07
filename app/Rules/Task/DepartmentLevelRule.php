<?php

namespace App\Rules\Task;

use App\Enum\OrgStructurLevel;
use App\Models\Master\OrgStructure;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DepartmentLevelRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $department = OrgStructure::find($value);

        if (! $department || $department->level !== OrgStructurLevel::LEVEL_DEPARTMENT->value) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
