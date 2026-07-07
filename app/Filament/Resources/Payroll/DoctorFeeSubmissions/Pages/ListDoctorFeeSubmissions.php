<?php

namespace App\Filament\Resources\Payroll\DoctorFeeSubmissions\Pages;

use App\Filament\Resources\Payroll\DoctorFeeSubmissions\DoctorFeeSubmissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDoctorFeeSubmissions extends ListRecords
{
    protected static string $resource = DoctorFeeSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
