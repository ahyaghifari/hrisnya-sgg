<?php

namespace App\Filament\Resources\Payroll\OvertimeRates\Pages;

use App\Filament\Resources\Payroll\OvertimeRates\OvertimeRatesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOvertimeRates extends ListRecords
{
    protected static string $resource = OvertimeRatesResource::class;

    protected static ?string $title = 'Tarif Lembur';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Tarif Lembur'),
        ];
    }
}
