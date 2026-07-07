<?php

namespace App\Filament\Resources\Payroll\DoctorFeeSubmissions\Pages;

use App\Filament\Resources\Payroll\DoctorFeeSubmissions\DoctorFeeSubmissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDoctorFeeSubmission extends CreateRecord
{
    protected static string $resource = DoctorFeeSubmissionResource::class;
}
