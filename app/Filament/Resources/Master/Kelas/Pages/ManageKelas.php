<?php

namespace App\Filament\Resources\Master\Kelas\Pages;

use App\Filament\Resources\Master\Kelas\KelasResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKelas extends ManageRecords
{
    protected static string $resource = KelasResource::class;

    protected static ?string $title = 'Kelas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Kelas'),
        ];
    }
}
