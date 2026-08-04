<?php

namespace App\Filament\Resources\Master\Religions\Pages;

use App\Filament\Resources\Master\Religions\ReligionsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageReligions extends ManageRecords
{
    protected static string $resource = ReligionsResource::class;

    protected static ?string $title = 'Agama';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Agama'),
        ];
    }
}
