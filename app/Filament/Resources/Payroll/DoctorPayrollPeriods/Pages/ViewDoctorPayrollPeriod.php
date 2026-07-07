<?php

namespace App\Filament\Resources\Payroll\DoctorPayrollPeriods\Pages;

use App\Filament\Resources\Payroll\DoctorPayrollPeriods\DoctorPayrollPeriodResource;
use App\Models\Payroll\DoctorPayroll;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDoctorPayrollPeriod extends ViewRecord
{
    protected static string $resource = DoctorPayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
