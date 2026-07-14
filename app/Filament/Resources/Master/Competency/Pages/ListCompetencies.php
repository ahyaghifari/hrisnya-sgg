<?php

namespace App\Filament\Resources\Master\Competency\Pages;

use App\Filament\Resources\Master\Competency\CompetencyResource;
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
