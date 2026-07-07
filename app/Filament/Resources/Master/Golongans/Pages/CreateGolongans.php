<?php

namespace App\Filament\Resources\Master\Golongans\Pages;

use App\Filament\Resources\Master\Golongans\GolongansResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGolongans extends CreateRecord
{
    protected static string $resource = GolongansResource::class;
    protected static ?string $title = 'Buat Golongan Baru';
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
