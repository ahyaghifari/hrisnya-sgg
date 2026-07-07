<?php

namespace App\Filament\Resources\Payroll\DoctorPayrollPeriods\Pages;

use App\Filament\Resources\Payroll\DoctorPayrollPeriods\DoctorPayrollPeriodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDoctorPayrollPeriods extends ListRecords
{
    protected static string $resource = DoctorPayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
