<?php

namespace App\Filament\Resources\Payroll\FeeSubmissions\Pages;

use App\Filament\Resources\Payroll\FeeSubmissions\FeeSubmissionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFeeSubmission extends ViewRecord
{
    protected static string $resource = FeeSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
