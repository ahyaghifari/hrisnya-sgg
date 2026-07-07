<?php

namespace App\Filament\Resources\Payroll\ExtraHourAllowances\Pages;

use App\Filament\Resources\Payroll\ExtraHourAllowances\ExtraHourAllowancesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExtraHourAllowances extends CreateRecord
{
    protected static string $resource = ExtraHourAllowancesResource::class;
    
    protected static ?string $title = 'Tambah Tunjangan Kelebihan Jam Kerja';

    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
