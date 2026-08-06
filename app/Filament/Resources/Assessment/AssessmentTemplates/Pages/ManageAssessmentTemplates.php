<?php

namespace App\Filament\Resources\Assessment\AssessmentTemplates\Pages;

use App\Filament\Resources\Assessment\AssessmentTemplates\AssessmentTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAssessmentTemplates extends ManageRecords
{
    protected static string $resource = AssessmentTemplateResource::class;

    protected static ?string $title = 'Template Penilaian';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Template Penilaian'),
        ];
    }
}
