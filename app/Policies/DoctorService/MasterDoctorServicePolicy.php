<?php

declare(strict_types=1);

namespace App\Policies\DoctorService;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DoctorService\MasterDoctorService;
use Illuminate\Auth\Access\HandlesAuthorization;

class MasterDoctorServicePolicy
{
    use HandlesAuthorization;
    
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

}