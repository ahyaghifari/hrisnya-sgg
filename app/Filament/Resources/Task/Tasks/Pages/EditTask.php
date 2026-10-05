<?php

namespace App\Filament\Resources\Task\Tasks\Pages;

use App\Filament\Resources\Task\Tasks\TaskResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** Pelaksana utama harus punya baris task_assignees (dibuat otomatis cuma saat tugas dibuat). */
    protected function afterSave(): void
    {
        if ($this->record->assigned_to) {
            $this->record->assignees()->firstOrCreate(['user_id' => $this->record->assigned_to]);
        }
    }
}
