<?php

declare(strict_types=1);

namespace App\Policies\Master;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Competencies;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompetenciesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Competencies');
    }

    public function view(AuthUser $authUser, Competencies $competencies): bool
    {
        return $authUser->can('View:Competencies');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Competencies');
    }

    public function update(AuthUser $authUser, Competencies $competencies): bool
    {
        return $authUser->can('Update:Competencies');
    }

    public function delete(AuthUser $authUser, Competencies $competencies): bool
    {
        return $authUser->can('Delete:Competencies');
    }

    public function restore(AuthUser $authUser, Competencies $competencies): bool
    {
        return $authUser->can('Restore:Competencies');
    }

    public function forceDelete(AuthUser $authUser, Competencies $competencies): bool
    {
        return $authUser->can('ForceDelete:Competencies');
    }

    public function restoreAny(AuthUser $authUser, Competencies $competencies): bool
    {
        return $authUser->can('RestoreAny:Competencies');
    }

}