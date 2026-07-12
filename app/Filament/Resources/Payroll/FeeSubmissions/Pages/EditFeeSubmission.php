<?php

namespace App\Filament\Resources\Payroll\FeeSubmissions\Pages;

use App\Filament\Resources\Payroll\FeeSubmissions\FeeSubmissionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFeeSubmission extends EditRecord
{
    protected static string $resource = FeeSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
