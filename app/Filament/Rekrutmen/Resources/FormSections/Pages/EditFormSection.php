<?php

namespace App\Filament\Rekrutmen\Resources\FormSections\Pages;

use App\Filament\Rekrutmen\Resources\FormSections\FormSectionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFormSection extends EditRecord
{
    protected static string $resource = FormSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
