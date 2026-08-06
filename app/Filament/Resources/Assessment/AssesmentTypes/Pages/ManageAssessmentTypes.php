<?php

namespace App\Filament\Resources\Assessment\AssesmentTypes\Pages;

use App\Filament\Resources\Assessment\AssesmentTypes\AssesmentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAssessmentTypes extends ManageRecords
{
    protected static string $resource = AssesmentTypeResource::class;

    protected static ?string $title = 'Tipe Penilaian';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Tipe Penilaian'),
        ];
    }
}
