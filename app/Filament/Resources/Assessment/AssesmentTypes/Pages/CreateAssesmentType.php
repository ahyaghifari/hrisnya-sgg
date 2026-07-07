<?php

namespace App\Filament\Resources\Assessment\AssesmentTypes\Pages;

use App\Filament\Resources\Assessment\AssesmentTypes\AssesmentTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssesmentType extends CreateRecord
{
    protected static string $resource = AssesmentTypeResource::class;
    protected static ?string $title = 'Buat Tipe Penilaian';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
