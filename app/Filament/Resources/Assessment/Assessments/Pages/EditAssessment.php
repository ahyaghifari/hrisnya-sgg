<?php

namespace App\Filament\Resources\Assessment\Assessments\Pages;

use App\Filament\Resources\Assessment\Assessments\AssessmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssessment extends EditRecord
{
    protected static string $resource = AssessmentResource::class;

    protected static ?string $title = 'Edit Penilaian';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [];
    }
}
