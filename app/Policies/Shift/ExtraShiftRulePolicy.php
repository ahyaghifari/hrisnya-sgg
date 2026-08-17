<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Shift\ExtraShiftRule;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExtraShiftRulePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExtraShiftRule');
    }

    public function view(AuthUser $authUser, ExtraShiftRule $extraShiftRule): bool
    {
        return $authUser->can('View:ExtraShiftRule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExtraShiftRule');
    }

    public function update(AuthUser $authUser, ExtraShiftRule $extraShiftRule): bool
    {
        return $authUser->can('Update:ExtraShiftRule');
    }

    public function delete(AuthUser $authUser, ExtraShiftRule $extraShiftRule): bool
    {
        return $authUser->can('Delete:ExtraShiftRule');
    }
}
