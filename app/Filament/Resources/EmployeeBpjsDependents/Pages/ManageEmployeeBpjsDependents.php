<?php

namespace App\Filament\Resources\EmployeeBpjsDependents\Pages;

use App\Filament\Resources\EmployeeBpjsDependents\EmployeeBpjsDependentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeBpjsDependents extends ManageRecords
{
    protected static string $resource = EmployeeBpjsDependentResource::class;

    protected static ?string $title = 'Tanggungan BPJS';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Tanggungan'),
        ];
    }
}
