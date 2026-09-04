<?php

namespace App\Filament\Rekrutmen\Resources\CompanyAgreements\Pages;

use App\Filament\Rekrutmen\Resources\CompanyAgreements\CompanyAgreementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCompanyAgreements extends ManageRecords
{
    protected static string $resource = CompanyAgreementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
