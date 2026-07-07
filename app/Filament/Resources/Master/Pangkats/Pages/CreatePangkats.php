<?php

namespace App\Filament\Resources\Master\Pangkats\Pages;

use App\Filament\Resources\Master\Pangkats\PangkatsResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePangkats extends CreateRecord
{
    protected static string $resource = PangkatsResource::class;
    protected static ?string $title = 'Buat Pangkat Baru';
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
