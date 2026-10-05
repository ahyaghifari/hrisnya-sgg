<?php

declare(strict_types=1);

namespace App\Policies\Task;

use App\Models\Task\Task;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/** Hanya melayani resource admin (Filament). Portal tugas memakai aturan TaskService sendiri. */
class TaskPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Task');
    }

    public function view(AuthUser $authUser, Task $task): bool
    {
        return $authUser->can('View:Task');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Task');
    }

    public function update(AuthUser $authUser, Task $task): bool
    {
        return $authUser->can('Update:Task');
    }

    public function delete(AuthUser $authUser, Task $task): bool
    {
        return $authUser->can('Delete:Task');
    }

    public function restore(AuthUser $authUser, Task $task): bool
    {
        return $authUser->can('Restore:Task');
    }

    public function forceDelete(AuthUser $authUser, Task $task): bool
    {
        return $authUser->can('ForceDelete:Task');
    }
}
