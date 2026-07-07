<?php

namespace App\Filament\Resources\ManPower\JobOpenings\Pages;

use App\Filament\Resources\ManPower\JobOpenings\JobOpeningResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJobOpening extends EditRecord
{
    protected static string $resource = JobOpeningResource::class;

    protected static ?string $title = 'Edit Lowongan Kerja';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
