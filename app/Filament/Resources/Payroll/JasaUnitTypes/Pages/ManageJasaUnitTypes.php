<?php

namespace App\Filament\Resources\Payroll\JasaUnitTypes\Pages;

use App\Filament\Resources\Payroll\JasaUnitTypes\JasaUnitTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJasaUnitTypes extends ManageRecords
{
    protected static string $resource = JasaUnitTypeResource::class;

    protected static ?string $title = 'Jasa Unit';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
