<?php

namespace App\Filament\Resources\Task\Tasks\Pages;

use App\Filament\Resources\Task\Tasks\TaskResource;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected static ?string $title = 'Tugas Individu';
}
