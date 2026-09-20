<?php

namespace App\Filament\Resources\Master\Holidays;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Master\Holidays\Pages\ManageHolidays;
use App\Models\Master\Attendance\Holidays;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class HolidayResource extends BaseResource
{
    protected static ?string $model = Holidays::class;

    protected static ?string $navigationLabel = 'Hari Libur & Cuti Bersama';

    protected static string|UnitEnum|null $navigationGroup = 'Presensi & Absensi';

    protected static ?int $navigationSort = 5; // lihat AttendanceRequestResource

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Hari Libur';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->native(false),

                Toggle::make('is_collective_leave')
                    ->label('Cuti Bersama')
                    ->helperText('Aktifkan kalau tanggal ini mengurangi jatah Cuti Tahunan karyawan. Nonaktif = libur nasional biasa, tidak mengurangi cuti.')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_collective_leave')
                    ->label('Cuti Bersama')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_collective_leave')
                    ->label('Cuti Bersama'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHolidays::route('/'),
        ];
    }
}
