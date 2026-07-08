<?php

declare(strict_types=1);

namespace App\Policies\DoctorService;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DoctorService\MasterDoctorService;
use Illuminate\Auth\Access\HandlesAuthorization;

class MasterDoctorServicePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MasterDoctorService');
    }

    public function view(AuthUser $authUser, MasterDoctorService $masterDoctorService): bool
    {
        return $authUser->can('View:MasterDoctorService');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MasterDoctorService');
    }

    public function update(AuthUser $authUser, MasterDoctorService $masterDoctorService): bool
    {
        return $authUser->can('Update:MasterDoctorService');
    }

    public function delete(AuthUser $authUser, MasterDoctorService $masterDoctorService): bool
    {
        return $authUser->can('Delete:MasterDoctorService');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MasterDoctorService');
    }

    public function restore(AuthUser $authUser, MasterDoctorService $masterDoctorService): bool
    {
        return $authUser->can('Restore:MasterDoctorService');
    }

    public function forceDelete(AuthUser $authUser, MasterDoctorService $masterDoctorService): bool
    {
        return $authUser->can('ForceDelete:MasterDoctorService');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MasterDoctorService');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MasterDoctorService');
    }

    public function replicate(AuthUser $authUser, MasterDoctorService $masterDoctorService): bool
    {
        return $authUser->can('Replicate:MasterDoctorService');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MasterDoctorService');
    }

}