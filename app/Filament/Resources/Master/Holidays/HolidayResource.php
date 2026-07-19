<?php

namespace App\Filament\Resources\Master\Holidays;

use App\Filament\Resources\Master\Holidays\Pages\ListHolidays;
use App\Filament\Resources\Master\Holidays\Schemas\HolidayForm;
use App\Filament\Resources\Master\Holidays\Tables\HolidaysTable;
use App\Models\Master\Attendance\Holidays;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HolidayResource extends Resource
{
    protected static ?string $model = Holidays::class;

    protected static ?string $navigationLabel = "Hari Libur & Cuti Bersama";

    protected static string | UnitEnum | null $navigationGroup = 'Presensi & Absensi';

    protected static ?int $navigationSort = 15;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    public static function form(Schema $schema): Schema
    {
        return HolidayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HolidaysTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHolidays::route('/'),
        ];
    }
}
