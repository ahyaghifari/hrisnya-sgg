<?php

namespace App\Helpers;

use App\Models\User;

class PermissionHelper
{
    public static function hasPermission(User $user, string $permission, ?int $companyId = null): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if ($companyId === '01J2F0C8KQ9M7D4S8RBX6E1ZVP') { // Global Company
            return $user->can($permission);
        }

        return $user->company_id === $companyId && $user->can($permission);
    }

    public static function hasGlobalRole(User $user): bool
    {
        return $user->company_id === '01J2F0C8KQ9M7D4S8RBX6E1ZVP';
    }

    public static function hasCompanyRole(User $user, string $role, int $companyId): bool
    {
        return $user->company_id === $companyId && $user->hasRole($role);
    }
}
