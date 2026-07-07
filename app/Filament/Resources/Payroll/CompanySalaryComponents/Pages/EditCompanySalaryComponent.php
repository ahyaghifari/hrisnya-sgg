<?php

namespace App\Filament\Resources\Payroll\CompanySalaryComponents\Pages;

use App\Filament\Resources\Payroll\CompanySalaryComponents\CompanySalaryComponentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompanySalaryComponent extends EditRecord
{
    protected static string $resource = CompanySalaryComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
