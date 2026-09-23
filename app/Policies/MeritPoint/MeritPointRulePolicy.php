<?php

declare(strict_types=1);

namespace App\Policies\MeritPoint;

use App\Models\MeritPoint\MeritPointRule;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Ditulis manual sesuai bentuk yang di-generate `php artisan shield:generate --resource=MeritPointRuleResource`
 * (baris permission ViewAny:MeritPointRule dkk masih perlu di-seed lewat command itu saat DB tersedia).
 */
class MeritPointRulePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MeritPointRule');
    }

    public function view(AuthUser $authUser, MeritPointRule $meritPointRule): bool
    {
        return $authUser->can('View:MeritPointRule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MeritPointRule');
    }

    public function update(AuthUser $authUser, MeritPointRule $meritPointRule): bool
    {
        return $authUser->can('Update:MeritPointRule');
    }

    public function delete(AuthUser $authUser, MeritPointRule $meritPointRule): bool
    {
        return $authUser->can('Delete:MeritPointRule');
    }

    public function restore(AuthUser $authUser, MeritPointRule $meritPointRule): bool
    {
        return $authUser->can('Restore:MeritPointRule');
    }

    public function forceDelete(AuthUser $authUser, MeritPointRule $meritPointRule): bool
    {
        return $authUser->can('ForceDelete:MeritPointRule');
    }
}
