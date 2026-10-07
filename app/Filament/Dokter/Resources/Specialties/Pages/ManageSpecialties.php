<?php

namespace App\Filament\Dokter\Resources\Specialties\Pages;

use App\Filament\Dokter\Resources\Specialties\SpecialtyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSpecialties extends ManageRecords
{
    protected static string $resource = SpecialtyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Spesialisasi')];
    }
}
