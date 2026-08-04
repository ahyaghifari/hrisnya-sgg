<?php

namespace App\Filament\Resources\Master\EmployeeTypes\Pages;

use App\Filament\Resources\Master\EmployeeTypes\EmployeeTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeTypes extends ManageRecords
{
    protected static string $resource = EmployeeTypeResource::class;

    protected static ?string $title = 'Tipe Karyawan';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Tipe Karyawan'),
        ];
    }
}
