<?php

namespace App\Filament\Resources\Payroll\DoctorFeeSubmissions\Pages;

use App\Filament\Resources\Payroll\DoctorFeeSubmissions\DoctorFeeSubmissionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDoctorFeeSubmission extends ViewRecord
{
    protected static string $resource = DoctorFeeSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
