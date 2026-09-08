<?php

namespace App\Filament\Rekrutmen\Resources\JobOpenings\Pages;

use App\Filament\Rekrutmen\Resources\JobOpenings\JobOpeningResource;
use Filament\Resources\Pages\ViewRecord;

class ViewJobOpening extends ViewRecord
{
    protected static string $resource = JobOpeningResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return JobOpeningResource::hydrateAgreements($this->record, $data);
    }
}
