<?php

namespace App\Filament\Resources\Master\Divisions\Pages;

use App\Filament\Resources\Master\Divisions\DivisionsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDivisions extends ListRecords
{
    protected static string $resource = DivisionsResource::class;

    protected static ?string $title = "Daftar Divisi";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Divisi')
                ->visible(DivisionsResource::canCreate()),
        ];
    }
}
