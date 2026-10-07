<?php

namespace App\Filament\Dokter\Resources\Doctors\Pages;

use App\Filament\Dokter\Resources\Doctors\DoctorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDoctors extends ListRecords
{
    protected static string $resource = DoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Dokter')];
    }
}
