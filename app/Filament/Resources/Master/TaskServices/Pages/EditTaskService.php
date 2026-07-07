<?php

namespace App\Filament\Resources\Master\TaskServices\Pages;

use App\Filament\Resources\Master\TaskServices\TaskServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTaskService extends EditRecord
{
    protected static string $resource = TaskServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
