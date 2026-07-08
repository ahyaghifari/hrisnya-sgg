<?php

declare(strict_types=1);

namespace App\Policies\Master\Task;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Task\TaskService;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskServicePolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, TaskService $taskService): bool
    {
        return $authUser->can('View:TaskService');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TaskService');
    }

    public function update(AuthUser $authUser, TaskService $taskService): bool
    {
        return $authUser->can('Update:TaskService');
    }

    public function delete(AuthUser $authUser, TaskService $taskService): bool
    {
        return $authUser->can('Delete:TaskService');
    }

}