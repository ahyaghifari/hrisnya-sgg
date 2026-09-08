<?php

namespace App\Filament\Rekrutmen\Resources\JobOpenings\Pages;

use App\Filament\Rekrutmen\Resources\JobOpenings\JobOpeningResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJobOpening extends EditRecord
{
    protected static string $resource = JobOpeningResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return JobOpeningResource::hydrateAgreements($this->record, $data);
    }

    protected function afterSave(): void
    {
        JobOpeningResource::persistAgreements($this->record, $this->data);
    }
}
