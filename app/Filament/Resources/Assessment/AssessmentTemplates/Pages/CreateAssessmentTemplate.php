<?php

namespace App\Filament\Resources\Assessment\AssessmentTemplates\Pages;

use App\Filament\Resources\Assessment\AssessmentTemplates\AssessmentTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssessmentTemplate extends CreateRecord
{
    protected static string $resource = AssessmentTemplateResource::class;
    protected static ?string $title = 'Buat Form Template Penilaian';
}
