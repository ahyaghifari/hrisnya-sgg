<?php

declare(strict_types=1);

namespace App\Policies\Task;

use App\Models\Task\TaskProject;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/** Hanya melayani resource admin (Filament). Portal tugas memakai aturan TaskService sendiri. */
class TaskProjectPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TaskProject');
    }

    public function view(AuthUser $authUser, TaskProject $project): bool
    {
        return $authUser->can('View:TaskProject');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TaskProject');
    }

    public function update(AuthUser $authUser, TaskProject $project): bool
    {
        return $authUser->can('Update:TaskProject');
    }

    public function delete(AuthUser $authUser, TaskProject $project): bool
    {
        return $authUser->can('Delete:TaskProject');
    }

    public function restore(AuthUser $authUser, TaskProject $project): bool
    {
        return $authUser->can('Restore:TaskProject');
    }

    public function forceDelete(AuthUser $authUser, TaskProject $project): bool
    {
        return $authUser->can('ForceDelete:TaskProject');
    }
}
