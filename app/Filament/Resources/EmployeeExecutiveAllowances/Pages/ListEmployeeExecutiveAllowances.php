<?php

namespace App\Filament\Resources\EmployeeExecutiveAllowances\Pages;

use App\Filament\Resources\EmployeeExecutiveAllowances\EmployeeExecutiveAllowanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeExecutiveAllowances extends ListRecords
{
    protected static string $resource = EmployeeExecutiveAllowanceResource::class;

    protected static ?string $title = 'Penggajian Eksekutif';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
