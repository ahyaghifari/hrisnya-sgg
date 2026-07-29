<?php

namespace App\Filament\Resources\Master\JobPositions\Pages;

use App\Filament\Resources\Master\JobPositions\JobPositionResource;
use Filament\Resources\Pages\EditRecord;

class EditJobPosition extends EditRecord
{
    protected static string $resource = JobPositionResource::class;

    protected static ?string $title = 'Edit Jabatan';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
