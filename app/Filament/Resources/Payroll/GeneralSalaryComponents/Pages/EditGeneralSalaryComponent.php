<?php

namespace App\Filament\Resources\Payroll\GeneralSalaryComponents\Pages;

use App\Filament\Resources\Payroll\GeneralSalaryComponents\GeneralSalaryComponentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGeneralSalaryComponent extends EditRecord
{
    protected static string $resource = GeneralSalaryComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
