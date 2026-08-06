<?php

declare(strict_types=1);

namespace App\Policies\Master;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\OrgStructure;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrgStructurePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OrgStructure');
    }

    public function view(AuthUser $authUser, OrgStructure $orgStructure): bool
    {
        return $authUser->can('View:OrgStructure');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OrgStructure');
    }

    public function update(AuthUser $authUser, OrgStructure $orgStructure): bool
    {
        return $authUser->can('Update:OrgStructure');
    }

    public function delete(AuthUser $authUser, OrgStructure $orgStructure): bool
    {
        return $authUser->can('Delete:OrgStructure');
    }

    public function restore(AuthUser $authUser, OrgStructure $orgStructure): bool
    {
        return $authUser->can('Restore:OrgStructure');
    }

}