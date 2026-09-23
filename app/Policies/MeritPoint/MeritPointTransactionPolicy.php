<?php

declare(strict_types=1);

namespace App\Policies\MeritPoint;

use App\Models\MeritPoint\MeritPointTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Ditulis manual sesuai bentuk yang di-generate `php artisan shield:generate --resource=MeritPointTransactionResource`
 * (baris permission ViewAny:MeritPointTransaction dkk masih perlu di-seed lewat command itu saat
 * DB tersedia). Resource-nya List+Create saja (ledger tidak pernah diedit), tapi policy tetap
 * lengkap sesuai pola Shield.
 */
class MeritPointTransactionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MeritPointTransaction');
    }

    public function view(AuthUser $authUser, MeritPointTransaction $meritPointTransaction): bool
    {
        return $authUser->can('View:MeritPointTransaction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MeritPointTransaction');
    }

    public function update(AuthUser $authUser, MeritPointTransaction $meritPointTransaction): bool
    {
        return $authUser->can('Update:MeritPointTransaction');
    }

    public function delete(AuthUser $authUser, MeritPointTransaction $meritPointTransaction): bool
    {
        return $authUser->can('Delete:MeritPointTransaction');
    }

    public function restore(AuthUser $authUser, MeritPointTransaction $meritPointTransaction): bool
    {
        return $authUser->can('Restore:MeritPointTransaction');
    }

    public function forceDelete(AuthUser $authUser, MeritPointTransaction $meritPointTransaction): bool
    {
        return $authUser->can('ForceDelete:MeritPointTransaction');
    }
}
