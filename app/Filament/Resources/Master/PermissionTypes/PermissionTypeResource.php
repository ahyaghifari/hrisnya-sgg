<?php

namespace App\Filament\Resources\Master\PermissionTypes;

use App\Enum\Attendance\PermissionGroupEnum;
use App\Enum\Attendance\PermissionTypeCategoryEnum;
use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Master\PermissionTypes\Pages\ManagePermissionTypes;
use App\Models\Master\Attendance\PermissionTypes;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PermissionTypeResource extends BaseCompanyResource
{
    protected static ?string $model = PermissionTypes::class;

    protected static ?string $navigationLabel = 'Jenis Izin';

    protected static string|UnitEnum|null $navigationGroup = 'Master Perusahaan';

    protected static ?int $navigationSort = 12;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Jenis Izin';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                TextInput::make('name')
                    ->label('Nama Tipe Izin')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                Select::make('category')
                    ->label('Kategori')
                    ->options(collect(PermissionGroupEnum::masterCategories())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->placeholder('Tidak ada kategori khusus')
                    ->helperText('Cuma dipakai untuk jenis izin di bawah kelompok Meninggalkan Jam Kerja (Terlambat/Pulang Cepat/Tengah Hari) & Izin Khusus - kelompok lain (Tidak Masuk Seharian, Sakit) tidak butuh master, kategorinya otomatis dari kelompok yang dipilih di form pengajuan.')
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->label('Keterangan/Deskripsi')
                    ->helperText('Opsional - tampil ke pemohon di bawah dropdown jenis izin saat mereka memilih tipe ini di form pengajuan (mis. syarat, jumlah hari, dokumen pendukung).')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),
                TextColumn::make('name')->label('Nama Tipe Izin')->searchable()->sortable(),
                TextColumn::make('category')->label('Kategori')->placeholder('-')
                    ->formatStateUsing(fn (?string $state) => $state ? PermissionTypeCategoryEnum::tryFrom($state)?->label() ?? $state : null),
            ])
            ->filters([
                static::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (PermissionTypes $record) => static::canDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePermissionTypes::route('/'),
        ];
    }
}
