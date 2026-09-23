<?php

declare(strict_types=1);

namespace App\Policies\Sp;

use App\Models\Sp\SpRecommendation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Ditulis manual sesuai bentuk yang di-generate `php artisan shield:generate --resource=SpRecommendationResource`
 * (baris permission ViewAny:SpRecommendation dkk masih perlu di-seed lewat command itu saat DB
 * tersedia). Resource-nya cuma List (Create/Update/Delete tidak dipakai - approve/reject row
 * action dicek lewat ViewAny/View saja), tapi method policy tetap lengkap sesuai pola Shield.
 */
class SpRecommendationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SpRecommendation');
    }

    public function view(AuthUser $authUser, SpRecommendation $spRecommendation): bool
    {
        return $authUser->can('View:SpRecommendation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SpRecommendation');
    }

    public function update(AuthUser $authUser, SpRecommendation $spRecommendation): bool
    {
        return $authUser->can('Update:SpRecommendation');
    }

    public function delete(AuthUser $authUser, SpRecommendation $spRecommendation): bool
    {
        return $authUser->can('Delete:SpRecommendation');
    }

    public function restore(AuthUser $authUser, SpRecommendation $spRecommendation): bool
    {
        return $authUser->can('Restore:SpRecommendation');
    }

    public function forceDelete(AuthUser $authUser, SpRecommendation $spRecommendation): bool
    {
        return $authUser->can('ForceDelete:SpRecommendation');
    }
}
