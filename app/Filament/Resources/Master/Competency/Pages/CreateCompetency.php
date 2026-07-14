<?php

namespace App\Filament\Resources\Master\Competency\Pages;

use App\Filament\Resources\Master\Competency\CompetencyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompetency extends CreateRecord
{
    protected static string $resource = CompetencyResource::class;
    protected static ?string $title = 'Buat Kompetensi Baru';
}
