<?php

namespace App\Filament\Candidate\Resources\CandidateProfiles\Pages;

use App\Filament\Candidate\Resources\CandidateProfiles\CandidateProfileResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCandidateProfile extends ViewRecord
{
    protected static string $resource = CandidateProfileResource::class;

    protected static ?string $title = 'Profil Saya';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
