<?php

namespace App\Filament\Resources\Payroll\Payrolls\Pages;

use App\Filament\Resources\Payroll\Payrolls\PayrollResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPayroll extends EditRecord
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
