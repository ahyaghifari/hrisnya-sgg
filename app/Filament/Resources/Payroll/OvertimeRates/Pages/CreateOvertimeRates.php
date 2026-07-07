<?php

namespace App\Filament\Resources\Payroll\OvertimeRates\Pages;

use App\Filament\Resources\Payroll\OvertimeRates\OvertimeRatesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOvertimeRates extends CreateRecord
{
    protected static string $resource = OvertimeRatesResource::class;

    protected static ?string $title = 'Buat Tarif Lembur';

    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
