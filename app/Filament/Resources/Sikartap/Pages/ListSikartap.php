<?php

namespace App\Filament\Resources\Sikartap\Pages;

use App\Filament\Resources\Sikartap\SikartapResource;
use Filament\Resources\Pages\ListRecords;

class ListSikartap extends ListRecords
{
    protected static string $resource = SikartapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
