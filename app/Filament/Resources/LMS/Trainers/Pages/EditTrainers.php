<?php

namespace App\Filament\Resources\LMS\Trainers\Pages;

use App\Filament\Resources\LMS\Trainers\TrainersResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrainers extends EditRecord
{
    protected static string $resource = TrainersResource::class;
    protected static ?string $title = 'Edit Pemateri';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(TrainersResource::canDelete($this->record)),
        ];
    }
}
