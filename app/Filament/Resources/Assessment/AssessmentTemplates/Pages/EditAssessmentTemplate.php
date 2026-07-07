<?php

namespace App\Filament\Resources\Assessment\AssessmentTemplates\Pages;

use App\Filament\Resources\Assessment\AssessmentTemplates\AssessmentTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssessmentTemplate extends EditRecord
{
    protected static string $resource = AssessmentTemplateResource::class;
    protected static ?string $title = 'Edit Form Template Penilaian';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => static::getResource()::canDelete($this->record)),
        ];
    }
}
