<?php

namespace App\Filament\Resources\Master\EmployeeTypes;

use App\Enum\EmployeeTypeCategory;
use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Master\EmployeeTypes\Pages\ManageEmployeeTypes;
use App\Models\Master\EmployeeTypes;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class EmployeeTypeResource extends BaseCompanyResource
{
    protected static ?string $model = EmployeeTypes::class;

    protected static ?string $navigationLabel = 'Tipe Karyawan';

    protected static string|UnitEnum|null $navigationGroup = 'Master Perusahaan';

    protected static ?int $navigationSort = 9;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Tipe Karyawan';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                TextInput::make('name')
                    ->label('Nama Tipe Karyawan')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Select::make('category')
                    ->label('Kategori')
                    ->helperText('Karyawan Orientasi cuma lihat tab Profil & Presensi di halaman Data Diri (tanpa Absensi/Riwayat Pengajuan/Dokumen/Penggajian).')
                    ->options(collect(EmployeeTypeCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()]))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),
                TextColumn::make('name')->label('Nama Tipe Karyawan')->searchable()->sortable(),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->placeholder('-')
                    ->color(fn ($state) => $state?->value === 'orientasi' ? 'warning' : 'gray'),
                TextColumn::make('user_profiles_count')
                    ->label('Jumlah Karyawan')
                    ->counts('userProfiles')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                static::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (EmployeeTypes $record) => static::canDelete($record)),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withCount('userProfiles');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployeeTypes::route('/'),
        ];
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ! $record->userProfiles()->exists();
    }
}
