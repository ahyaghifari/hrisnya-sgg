<?php

namespace App\Filament\Resources\Master\AttendanceDevices;

use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Master\AttendanceDevices\Pages\ManageAttendanceDevices;
use App\Models\Master\Attendance\AttendanceDevice;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AttendanceDeviceResource extends BaseCompanyResource
{
    protected static ?string $model = AttendanceDevice::class;

    protected static ?string $navigationLabel = 'Device Absensi';

    protected static string|UnitEnum|null $navigationGroup = 'Presensi & Absensi';

    protected static ?int $navigationSort = 17;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Device Absensi';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                TextInput::make('name')
                    ->label('Nama Device')
                    ->helperText('Harus sama persis dengan nama device di HikCentral.')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),
                TextColumn::make('name')->label('Nama Device')->searchable()->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                static::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (AttendanceDevice $record) => static::canDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAttendanceDevices::route('/'),
        ];
    }
}
