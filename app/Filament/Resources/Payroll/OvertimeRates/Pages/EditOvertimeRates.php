<?php

namespace App\Filament\Resources\Payroll\OvertimeRates\Pages;

use App\Filament\Resources\Payroll\OvertimeRates\OvertimeRatesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOvertimeRates extends EditRecord
{
    protected static string $resource = OvertimeRatesResource::class;

    protected static ?string $title = 'Edit Tarif Lembur';

    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
