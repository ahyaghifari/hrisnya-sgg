<?php

namespace App\Filament\Candidate\Resources\CandidateProfiles\Pages;

use App\Filament\Candidate\Resources\CandidateProfiles\CandidateProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCandidateProfiles extends ListRecords
{
    protected static string $resource = CandidateProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
