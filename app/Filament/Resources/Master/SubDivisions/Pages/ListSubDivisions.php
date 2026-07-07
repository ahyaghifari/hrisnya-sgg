<?php

namespace App\Filament\Resources\Master\SubDivisions\Pages;

use App\Filament\Resources\Master\SubDivisions\SubDivisionsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubDivisions extends ListRecords
{
    protected static string $resource = SubDivisionsResource::class;

    protected static ?string $title = "Data Sub Divisi";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label("Buat Sub Divisi")
                ->visible(fn () => SubDivisionsResource::canCreate()),
        ];
    }
}
