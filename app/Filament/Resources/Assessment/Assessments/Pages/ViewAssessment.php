<?php

namespace App\Filament\Resources\Assessment\Assessments\Pages;

use App\Filament\Resources\Assessment\Assessments\AssessmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Alignment;

class ViewAssessment extends ViewRecord
{
    protected static string $resource = AssessmentResource::class;
    protected static ?string $title = 'Detail Penilaian';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => AssessmentResource::canEdit($this->record))
        ];
    }
}
