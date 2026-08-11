<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payroll\JasaUnitType;
use Illuminate\Auth\Access\HandlesAuthorization;

class JasaUnitTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JasaUnitType');
    }

    public function view(AuthUser $authUser, JasaUnitType $jasaUnitType): bool
    {
        return $authUser->can('View:JasaUnitType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JasaUnitType');
    }

    public function update(AuthUser $authUser, JasaUnitType $jasaUnitType): bool
    {
        return $authUser->can('Update:JasaUnitType');
    }

    public function delete(AuthUser $authUser, JasaUnitType $jasaUnitType): bool
    {
        return $authUser->can('Delete:JasaUnitType');
    }

    public function restore(AuthUser $authUser, JasaUnitType $jasaUnitType): bool
    {
        return $authUser->can('Restore:JasaUnitType');
    }

    public function forceDelete(AuthUser $authUser, JasaUnitType $jasaUnitType): bool
    {
        return $authUser->can('ForceDelete:JasaUnitType');
    }

}