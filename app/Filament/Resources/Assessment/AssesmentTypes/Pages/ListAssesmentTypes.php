<?php

namespace App\Filament\Resources\Assessment\AssesmentTypes\Pages;

use App\Filament\Resources\Assessment\AssesmentTypes\AssesmentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssesmentTypes extends ListRecords
{
    protected static string $resource = AssesmentTypeResource::class;

    protected static ?string $title = 'Daftar Tipe Penilaian';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Tipe Penilaian')
                ->slideOver()
                ->modalWidth('md')
                ->modalHeading('Buat Tipe Penilaian'),
        ];
    }
}
