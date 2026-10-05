<?php

namespace App\Filament\Resources\Task\Projects\Pages;

use App\Filament\Resources\Task\Projects\TaskProjectResource;
use Filament\Resources\Pages\ListRecords;

class ListTaskProjects extends ListRecords
{
    protected static string $resource = TaskProjectResource::class;

    protected static ?string $title = 'Board Tugas';
}
