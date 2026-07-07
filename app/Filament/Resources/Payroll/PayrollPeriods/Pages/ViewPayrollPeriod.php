<?php

namespace App\Filament\Resources\Payroll\PayrollPeriods\Pages;

use App\Filament\Resources\Payroll\PayrollPeriods\PayrollPeriodResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPayrollPeriod extends ViewRecord
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
