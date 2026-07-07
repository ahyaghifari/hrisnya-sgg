<?php

namespace App\Filament\Resources\Payroll\PangkatSalaryComponents\Pages;

use App\Filament\Resources\Payroll\PangkatSalaryComponents\PangkatSalaryComponentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPangkatSalaryComponent extends EditRecord
{
    protected static string $resource = PangkatSalaryComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
