<?php

namespace App\Filament\Resources\Payroll\JobPositionSalaryComponents\Pages;

use App\Filament\Resources\Payroll\JobPositionSalaryComponents\SalaryComponentsResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalaryComponents extends EditRecord
{
    protected static string $resource = SalaryComponentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
