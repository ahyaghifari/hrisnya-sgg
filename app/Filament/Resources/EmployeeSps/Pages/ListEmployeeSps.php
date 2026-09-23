<?php

namespace App\Filament\Resources\EmployeeSps\Pages;

use App\Filament\Resources\EmployeeSps\EmployeeSpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeSps extends ListRecords
{
    protected static string $resource = EmployeeSpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
