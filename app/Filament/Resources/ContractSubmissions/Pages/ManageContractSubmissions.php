<?php

namespace App\Filament\Resources\ContractSubmissions\Pages;

use App\Filament\Resources\ContractSubmissions\ContractSubmissionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageContractSubmissions extends ManageRecords
{
    protected static string $resource = ContractSubmissionResource::class;

    protected static ?string $title = 'Pengajuan Perpanjangan Kontrak';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
