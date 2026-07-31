<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use App\Models\Shift\ExtraShiftAssignment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Permission manual (5 verb, bukan pola shield:generate 9-verb) - sama pola BpjsDependentPolicy/
 * UserKoperasiPolicy, baris permission-nya di-seed lewat CustomPermissionsSeeder.
 */
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

    public function update(AuthUser $authUser, ?ExtraShiftAssignment $extraShiftAssignment = null): bool
    {
        return $authUser->can('Update:ExtraShiftAssignment');
    }

    public function delete(AuthUser $authUser, ?ExtraShiftAssignment $extraShiftAssignment = null): bool
    {
        return $authUser->can('Delete:ExtraShiftAssignment');
    }
}
