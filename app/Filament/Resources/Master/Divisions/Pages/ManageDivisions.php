<?php

namespace App\Filament\Resources\Master\Divisions\Pages;

use App\Filament\Resources\Master\Divisions\DivisionsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDivisions extends ManageRecords
{
    protected static string $resource = DivisionsResource::class;

    protected static ?string $title = 'Divisi';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Divisi'),
        ];
    }
}
