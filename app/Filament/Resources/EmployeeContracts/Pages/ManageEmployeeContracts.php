<?php

namespace App\Filament\Resources\EmployeeContracts\Pages;

use App\Filament\Resources\EmployeeContracts\EmployeeContractResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeContracts extends ManageRecords
{
    protected static string $resource = EmployeeContractResource::class;

    protected static ?string $title = 'Data Kontrak';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Kontrak'),
        ];
    }
}
