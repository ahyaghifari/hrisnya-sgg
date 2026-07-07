<?php

namespace App\Filament\Resources\Payroll\ProgramIncentives\Pages;

use App\Filament\Resources\Payroll\ProgramIncentives\ProgramIncentiveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProgramIncentives extends ListRecords
{
    protected static string $resource = ProgramIncentiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->modalWidth('md'),
        ];
    }
}
