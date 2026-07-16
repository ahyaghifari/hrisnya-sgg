<?php

namespace App\Filament\Resources\Master\Holidays\Pages;

use App\Filament\Resources\Master\Holidays\HolidayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHolidays extends ListRecords
{
    protected static string $resource = HolidayResource::class;
    protected static ?string $title = 'Hari Libur & Cuti Bersama';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
