<?php

namespace App\Filament\Resources\Master\Departments\Pages;

use App\Filament\Resources\Master\Departments\DepartmentsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDepartments extends ManageRecords
{
    protected static string $resource = DepartmentsResource::class;

    protected static ?string $title = 'Departemen';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Departemen'),
        ];
    }
}
