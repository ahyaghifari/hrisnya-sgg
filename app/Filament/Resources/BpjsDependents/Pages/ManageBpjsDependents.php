<?php

namespace App\Filament\Resources\BpjsDependents\Pages;

use App\Filament\Resources\BpjsDependents\BpjsDependentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBpjsDependents extends ManageRecords
{
    protected static string $resource = BpjsDependentResource::class;

    protected static ?string $title = 'Tanggungan BPJS';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Tanggungan'),
        ];
    }
}
