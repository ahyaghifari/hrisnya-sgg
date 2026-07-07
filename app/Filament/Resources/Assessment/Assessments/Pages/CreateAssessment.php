<?php

namespace App\Filament\Resources\Assessment\Assessments\Pages;

use App\Filament\Resources\Assessment\Assessments\AssessmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssessment extends CreateRecord
{
    protected static string $resource = AssessmentResource::class;

    protected static ?string $title = 'Buat Penilaian';

    public function getRelationManagers(): array
    {
        return [];
    }
}
