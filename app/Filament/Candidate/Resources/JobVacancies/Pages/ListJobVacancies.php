<?php

namespace App\Filament\Candidate\Resources\JobVacancies\Pages;

use App\Filament\Candidate\Resources\JobVacancies\JobVacancyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobVacancies extends ListRecords
{
    protected static string $resource = JobVacancyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
