<?php

namespace App\Filament\Resources\LMS\TrainerExternals\Pages;

use App\Filament\Resources\LMS\TrainerExternals\TrainerExternalResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrainerExternal extends EditRecord
{
    protected static string $resource = TrainerExternalResource::class;
    protected static ?string $title = 'Edit Trainer Eksternal';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Hapus Trainer Eksternal'),
        ];
    }
}
