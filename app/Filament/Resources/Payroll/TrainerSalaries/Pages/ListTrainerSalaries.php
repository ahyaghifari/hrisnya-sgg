<?php

namespace App\Filament\Resources\Payroll\TrainerSalaries\Pages;

use App\Filament\Resources\Payroll\TrainerSalaries\TrainerSalaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrainerSalaries extends ListRecords
{
    protected static string $resource = TrainerSalaryResource::class;
    protected static ?string $title = 'Gaji Trainer';
}
