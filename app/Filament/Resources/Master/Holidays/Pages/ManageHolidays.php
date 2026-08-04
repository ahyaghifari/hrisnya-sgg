<?php

namespace App\Filament\Resources\Master\Holidays\Pages;

use App\Filament\Resources\Master\Holidays\HolidayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHolidays extends ManageRecords
{
    protected static string $resource = HolidayResource::class;

    protected static ?string $title = 'Hari Libur & Cuti Bersama';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Hari Libur'),
        ];
    }
}
