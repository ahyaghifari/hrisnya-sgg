<?php

namespace App\Filament\Resources\Master\AttendanceDevices\Pages;

use App\Filament\Resources\Master\AttendanceDevices\AttendanceDeviceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAttendanceDevices extends ManageRecords
{
    protected static string $resource = AttendanceDeviceResource::class;

    protected static ?string $title = 'Device Absensi';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Device Absensi'),
        ];
    }
}
