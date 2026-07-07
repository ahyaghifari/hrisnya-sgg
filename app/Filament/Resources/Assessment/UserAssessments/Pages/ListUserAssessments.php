<?php

namespace App\Filament\Resources\Assessment\UserAssessments\Pages;

use App\Filament\Resources\Assessment\UserAssessments\UserAssessmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserAssessments extends ListRecords
{
    protected static string $resource = UserAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
