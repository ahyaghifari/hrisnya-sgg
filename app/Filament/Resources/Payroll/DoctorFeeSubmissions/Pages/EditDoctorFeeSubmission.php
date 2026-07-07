<?php

namespace App\Filament\Resources\Payroll\DoctorFeeSubmissions\Pages;

use App\Filament\Resources\Payroll\DoctorFeeSubmissions\DoctorFeeSubmissionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDoctorFeeSubmission extends EditRecord
{
    protected static string $resource = DoctorFeeSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
