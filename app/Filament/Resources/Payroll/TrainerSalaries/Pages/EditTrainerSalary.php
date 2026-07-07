<?php

namespace App\Filament\Resources\Payroll\TrainerSalaries\Pages;

use App\Filament\Resources\Payroll\TrainerSalaries\TrainerSalaryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrainerSalary extends EditRecord
{
    protected static string $resource = TrainerSalaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
