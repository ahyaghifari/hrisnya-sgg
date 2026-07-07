<?php

namespace App\Filament\Resources\Master\Kelas\Pages;

use App\Filament\Resources\Master\Kelas\KelasResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKelas extends CreateRecord
{
    protected static string $resource = KelasResource::class;
    protected static ?string $title = 'Buat Kelas Baru';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
