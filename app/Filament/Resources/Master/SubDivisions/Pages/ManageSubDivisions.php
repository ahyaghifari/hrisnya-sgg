<?php

namespace App\Filament\Resources\Master\SubDivisions\Pages;

use App\Filament\Resources\Master\SubDivisions\SubDivisionsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSubDivisions extends ManageRecords
{
    protected static string $resource = SubDivisionsResource::class;

    protected static ?string $title = 'Sub Divisi';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Sub Divisi'),
        ];
    }
}
