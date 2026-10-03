<?php

namespace App\Filament\Persuratan\Resources\Perusahaans;

use App\Filament\Persuratan\Resources\Perusahaans\Pages\ManagePerusahaans;
use App\Models\Master\Companies;
use App\Models\Persuratan\Perusahaan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Master penerbit surat. Tidak 1:1 dengan m_companies HRIS: `hris_company_id` opsional
 * (kosong = penerbit di luar HRIS). Kode dipakai di nomor surat (`.../{kode_surat}/...`) — jadi
 * tidak boleh diganti sembarangan setelah ada surat yang terbit.
 */
class PerusahaanResource extends Resource
{
    protected static ?string $model = Perusahaan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Perusahaan';

    protected static ?string $modelLabel = 'Perusahaan';

    protected static ?string $pluralModelLabel = 'Perusahaan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode_surat')->label('Kode Surat')->required()->maxLength(20)->unique(ignoreRecord: true)
                ->helperText('Muncul di nomor surat, mis. SGG, RSUSM. Jangan diubah kalau sudah ada surat terbit.'),
            TextInput::make('nama')->label('Nama Perusahaan')->required()->maxLength(255),
            Select::make('hris_company_id')->label('Perusahaan di HRIS')
                ->options(fn () => Companies::orderBy('name')->pluck('name', 'id'))
                ->searchable()->placeholder('— Tidak ada di HRIS —')
                ->helperText('Opsional. Kosongkan untuk penerbit di luar HRIS.'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_surat')->label('Kode Surat')->badge()->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('hris_company_id')->label('Di HRIS')
                    ->formatStateUsing(fn (Perusahaan $r) => $r->hrisCompany()?->name ?? '—'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('kode_surat')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePerusahaans::route('/')];
    }
}
