<?php

namespace App\Filament\Resources\Master\AttendanceDevices;

use App\Filament\Resources\Master\AttendanceDevices\Pages\ListAttendanceDevices;
use App\Filament\Resources\Master\AttendanceDevices\Schemas\AttendanceDeviceForm;
use App\Filament\Resources\Master\AttendanceDevices\Tables\AttendanceDevicesTable;
use App\Models\Master\Attendance\AttendanceDevice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AttendanceDeviceResource extends Resource
{
    protected static ?string $model = AttendanceDevice::class;

    protected static ?string $navigationLabel = 'Device Absensi';

    protected static string | UnitEnum | null $navigationGroup = 'Master Perusahaan';

    protected static ?int $navigationSort = 17;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    public static function form(Schema $schema): Schema
    {
        return AttendanceDeviceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceDevicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceDevices::route('/'),
        ];
    }
}
