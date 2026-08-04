<?php

namespace App\Filament\Resources\Master\Competency\Pages;

use App\Filament\Resources\Master\Competency\CompetencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCompetencies extends ManageRecords
{
    protected static string $resource = CompetencyResource::class;

    protected static ?string $title = 'Kompetensi';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Kompetensi'),
        ];
    }
}
