<?php

namespace App\Filament\Resources\Competency\Competencies\Pages;

use App\Filament\Resources\Competency\Competencies\CompetencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompetencies extends ListRecords
{
    protected static string $resource = CompetencyResource::class;
    protected static ?string $title = 'Daftar Kompetensi';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Kompetensi Baru')
                ->modalHeading('Buat Kompetensi Baru')
        ];
    }
}
