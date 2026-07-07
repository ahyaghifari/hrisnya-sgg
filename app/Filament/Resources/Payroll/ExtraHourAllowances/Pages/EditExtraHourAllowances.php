<?php

namespace App\Filament\Resources\Payroll\ExtraHourAllowances\Pages;

use App\Filament\Resources\Payroll\ExtraHourAllowances\ExtraHourAllowancesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExtraHourAllowances extends EditRecord
{
    protected static string $resource = ExtraHourAllowancesResource::class;

    protected static ?string $title = 'Edit Tunjangan Kelebihan Jam Kerja';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
