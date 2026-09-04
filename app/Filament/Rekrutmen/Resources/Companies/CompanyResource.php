<?php

namespace App\Filament\Rekrutmen\Resources\Companies;

use App\Filament\Rekrutmen\Resources\Companies\Pages\ManageCompanies;
use App\Models\Rekrutmen\Company;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Perusahaan (rekrutmen) — port dari CompanyResource project `rekrutmen` (FL3 -> FL4), DB
 * `recruitment`. SET TERPISAH dari m_companies HRIS (jembatan `hris_code`). Read-mostly:
 * nama & kode HRIS tidak diedit di sini, yang penting `requires_mcu` (menentukan alur MCU)
 * + kontak + warna brand. Upload logo/hero ditunda (butuh disk publik bersama).
 */
class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Perusahaan';

    protected static ?string $modelLabel = 'Perusahaan';

    protected static ?string $pluralModelLabel = 'Perusahaan';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('ViewAny:JobOpenings');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas')->schema([
                TextInput::make('name')->label('Nama Perusahaan')->disabled(),
                TextInput::make('hris_code')->label('Kode HRIS')->disabled()->helperText('Jembatan ke m_companies HRIS.'),
            ])->columns(2),

            Section::make('Alur & Tampilan')->schema([
                Toggle::make('requires_mcu')->label('Wajib MCU')
                    ->helperText('Kalau nonaktif, kandidat lolos wawancara langsung ke Onboarding (lompati MCU).'),
                Toggle::make('is_active')->label('Perusahaan Aktif')
                    ->helperText('Nonaktif = lowongan disembunyikan dari halaman publik.'),
                ColorPicker::make('brand_color')->label('Warna Brand'),
            ])->columns(2),

            Section::make('Kontak')->schema([
                TextInput::make('email')->label('Email Kontak')->email(),
                TextInput::make('phone')->label('Telepon / WA')->tel(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('hris_code')->label('Kode HRIS')->badge(),
                IconColumn::make('requires_mcu')->label('Wajib MCU')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('email')->label('Email')->searchable()->placeholder('—'),
                TextColumn::make('phone')->label('Telepon')->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCompanies::route('/'),
        ];
    }
}
