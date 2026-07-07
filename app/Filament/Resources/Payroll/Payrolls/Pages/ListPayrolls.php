<?php

namespace App\Filament\Resources\Payroll\Payrolls\Pages;

use App\Filament\Resources\Payroll\PayrollPeriods\PayrollPeriodResource;
use App\Filament\Resources\Payroll\Payrolls\PayrollResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayrolls extends ListRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('Create Payroll')
                ->label('Buat Payroll')
                ->icon('heroicon-o-plus')
                ->url(PayrollPeriodResource::getUrl('index'))
        ];
    }
}
