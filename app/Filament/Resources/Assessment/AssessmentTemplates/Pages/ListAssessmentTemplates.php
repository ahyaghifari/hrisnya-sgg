<?php

namespace App\Filament\Resources\Assessment\AssessmentTemplates\Pages;

use App\Filament\Resources\Assessment\AssessmentTemplates\AssessmentTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssessmentTemplates extends ListRecords
{
    protected static string $resource = AssessmentTemplateResource::class;
    protected static ?string $title = 'Daftar Form Template Penilaian';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Template Baru')
                ->visible(fn (): bool => static::getResource()::canCreate()),
        ];
    }
}
