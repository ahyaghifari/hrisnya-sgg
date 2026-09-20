<?php

namespace App\Rules\Task;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SameDepartmentAndCompanyRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $currentUser = auth()->user();
        $reviewer = User::with(['company', 'profile.jobPosition.orgStructure.parent.parent'])->find($value);

        if (! $reviewer) {
            $fail('The selected SLA reviewer is invalid.');
            return;
        }

        if ($currentUser->id === $reviewer->id) {
            $fail('You cannot select yourself as the SLA reviewer.');
            return;
        }

        if ($currentUser->company_id !== $reviewer->company_id) {
            $fail('The selected SLA reviewer must be in the same company.');
            return;
        }

        $currentDept = $currentUser->profile?->department?->id;
        $reviewerDept = $reviewer->profile?->department?->id;

        if (! $currentDept || ! $reviewerDept || $currentDept !== $reviewerDept) {
            $fail('The selected SLA reviewer must be in the same department.');
        }
    }
}
