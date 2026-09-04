<?php

namespace App\Filament\Rekrutmen\Resources\FormSections\Pages;

use App\Filament\Rekrutmen\Resources\FormSections\FormSectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFormSections extends ListRecords
{
    protected static string $resource = FormSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
