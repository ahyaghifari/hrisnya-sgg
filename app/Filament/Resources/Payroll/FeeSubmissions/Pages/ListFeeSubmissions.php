<?php

namespace App\Filament\Resources\Payroll\FeeSubmissions\Pages;

use App\Filament\Resources\Payroll\FeeSubmissions\FeeSubmissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeeSubmissions extends ListRecords
{
    protected static string $resource = FeeSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
