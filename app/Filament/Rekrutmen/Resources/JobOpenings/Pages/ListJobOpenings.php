<?php

namespace App\Filament\Rekrutmen\Resources\JobOpenings\Pages;

use App\Filament\Rekrutmen\Resources\JobOpenings\JobOpeningResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobOpenings extends ListRecords
{
    protected static string $resource = JobOpeningResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
