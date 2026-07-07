<?php

namespace App\Repositories\Task;

use App\Models\Master\Task\TaskService;
use Illuminate\Database\Eloquent\Collection;

class TaskServiceRepository
{
    public function getAllTaskServices(): Collection
    {
        return TaskService::all();
    }
}
