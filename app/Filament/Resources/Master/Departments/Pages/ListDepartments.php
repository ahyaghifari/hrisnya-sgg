<?php

namespace App\Filament\Resources\Master\Departments\Pages;

use App\Filament\Resources\Master\Departments\DepartmentsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDepartments extends ListRecords
{
    protected static string $resource = DepartmentsResource::class;

    protected static ?string $title = "Daftar Departemen";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Departemen')->visible(DepartmentsResource::canCreate()),
        ];
    }
}
