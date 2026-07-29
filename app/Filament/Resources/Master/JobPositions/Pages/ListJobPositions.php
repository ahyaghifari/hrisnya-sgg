<?php

namespace App\Filament\Resources\Master\JobPositions\Pages;

use App\Filament\Resources\Master\JobPositions\JobPositionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobPositions extends ListRecords
{
    protected static string $resource = JobPositionResource::class;

    protected static ?string $title = 'Jabatan';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Ajukan Jabatan'),
        ];
    }
}
