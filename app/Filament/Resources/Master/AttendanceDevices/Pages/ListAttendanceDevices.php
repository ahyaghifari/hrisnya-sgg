<?php

namespace App\Filament\Resources\Master\AttendanceDevices\Pages;

use App\Filament\Resources\Master\AttendanceDevices\AttendanceDeviceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListAttendanceDevices extends ListRecords
{
    protected static string $resource = AttendanceDeviceResource::class;
    protected static ?string $title = 'Daftar Device Absensi';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Device Absensi')
                ->slideOver()
                ->modalWidth(Width::Medium)
                ->modalHeading('Tambah Device Absensi'),
        ];
    }
}
