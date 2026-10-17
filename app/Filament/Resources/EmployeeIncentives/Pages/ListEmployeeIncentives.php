<?php

namespace App\Filament\Resources\EmployeeIncentives\Pages;

use App\Filament\Resources\EmployeeIncentives\EmployeeIncentiveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeIncentives extends ListRecords
{
    protected static string $resource = EmployeeIncentiveResource::class;

    protected static ?string $title = 'Insentif';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
        ];
    }
}
