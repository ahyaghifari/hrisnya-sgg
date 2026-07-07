<?php

namespace App\Filament\Resources\Master\TaskServices\Pages;

use App\Filament\Resources\Master\TaskServices\TaskServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaskService extends CreateRecord
{
    protected static string $resource = TaskServiceResource::class;
}
