<?php

declare(strict_types=1);

namespace App\Policies\Task;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Task\Task;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
{
    use HandlesAuthorization;
    
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

}