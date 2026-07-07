<?php

namespace App\Filament\Resources\Competency\Competencies\Pages;

use App\Filament\Resources\Competency\Competencies\CompetencyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompetency extends CreateRecord
{
    protected static string $resource = CompetencyResource::class;
    protected static ?string $title = 'Buat Kompetensi Baru';
}
