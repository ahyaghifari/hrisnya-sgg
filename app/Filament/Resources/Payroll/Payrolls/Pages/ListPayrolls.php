<?php

namespace App\Filament\Resources\Payroll\Payrolls\Pages;

use App\Filament\Resources\Payroll\Payrolls\PayrollResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayrolls extends ListRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Payroll'),
        ];
    }
}
