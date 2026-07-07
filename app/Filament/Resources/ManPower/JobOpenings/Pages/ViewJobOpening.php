<?php

namespace App\Filament\Resources\ManPower\JobOpenings\Pages;

use App\Filament\Resources\ManPower\JobOpenings\JobOpeningResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewJobOpening extends ViewRecord
{
    protected static string $resource = JobOpeningResource::class;
    protected static ?string $title = 'Detail Lowongan Kerja';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Edit Lowongan Kerja')
                ->visible(fn ($record) => JobOpeningResource::canEdit($record)),
        ];
    }
}
