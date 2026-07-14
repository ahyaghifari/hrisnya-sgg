<?php

declare(strict_types=1);

namespace App\Policies\LMS\Trainer;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LMS\Trainer\Trainers;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainersPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Trainers');
    }

    public function view(AuthUser $authUser, Trainers $trainers): bool
    {
        return $authUser->can('View:Trainers');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Trainers');
    }

    public function update(AuthUser $authUser, Trainers $trainers): bool
    {
        return $authUser->can('Update:Trainers');
    }

    public function delete(AuthUser $authUser, Trainers $trainers): bool
    {
        return $authUser->can('Delete:Trainers');
    }

    public function restore(AuthUser $authUser, Trainers $trainers): bool
    {
        return $authUser->can('Restore:Trainers');
    }

    public function forceDelete(AuthUser $authUser, Trainers $trainers): bool
    {
        return $authUser->can('ForceDelete:Trainers');
    }

    public function restoreAny(AuthUser $authUser, Trainers $trainers): bool
    {
        return $authUser->can('RestoreAny:Trainers');
    }

    public function replicate(AuthUser $authUser, Trainers $trainers): bool
    {
        return $authUser->can('Replicate:Trainers');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Trainers');
    }

}