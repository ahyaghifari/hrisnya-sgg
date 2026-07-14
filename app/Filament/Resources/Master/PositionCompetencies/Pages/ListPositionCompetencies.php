<?php

namespace App\Filament\Resources\Master\PositionCompetencies\Pages;

use App\Filament\Resources\Master\PositionCompetencies\PositionCompetencyResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPositionCompetencies extends ListRecords
{
    protected static string $resource = PositionCompetencyResource::class;
    protected static ?string $title = 'Daftar Jabatan & Kompetensi';

    protected function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('competencies');
    }
}
