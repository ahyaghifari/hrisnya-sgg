<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Shift\ExtraShiftAssignment;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExtraShiftAssignmentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExtraShiftAssignment');
    }

    public function view(AuthUser $authUser, ExtraShiftAssignment $extraShiftAssignment): bool
    {
        return $authUser->can('View:ExtraShiftAssignment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExtraShiftAssignment');
    }

    public function update(AuthUser $authUser, ExtraShiftAssignment $extraShiftAssignment): bool
    {
        return $authUser->can('Update:ExtraShiftAssignment');
    }

    public function delete(AuthUser $authUser, ExtraShiftAssignment $extraShiftAssignment): bool
    {
        return $authUser->can('Delete:ExtraShiftAssignment');
    }

    public function restore(AuthUser $authUser, ExtraShiftAssignment $extraShiftAssignment): bool
    {
        return $authUser->can('Restore:ExtraShiftAssignment');
    }

}