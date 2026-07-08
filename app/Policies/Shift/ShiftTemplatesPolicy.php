<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Shift\ShiftTemplates;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShiftTemplatesPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, ShiftTemplates $shiftTemplates): bool
    {
        return $authUser->can('View:ShiftTemplates');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ShiftTemplates');
    }

    public function update(AuthUser $authUser, ShiftTemplates $shiftTemplates): bool
    {
        return $authUser->can('Update:ShiftTemplates');
    }

    public function delete(AuthUser $authUser, ShiftTemplates $shiftTemplates): bool
    {
        return $authUser->can('Delete:ShiftTemplates');
    }

}