<?php

namespace App\Filament\Persuratan\Resources\Units;

use App\Filament\Persuratan\Resources\Units\Pages\ManageUnits;
use App\Models\Persuratan\Perusahaan;
use App\Models\Persuratan\Unit;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Master kode unit untuk nomor surat. Kode TIDAK unik (sistem lama punya kode kembar antar unit). */
class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $modelLabel = 'Unit';

    protected static ?string $pluralModelLabel = 'Unit';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('perusahaan_id')->label('Perusahaan')->required()
                ->options(fn () => Perusahaan::orderBy('kode_surat')->pluck('nama', 'id'))
                // Perusahaan surat diturunkan dari unit, jadi memindahkan unit yang sudah dipakai akan mengubah perusahaan surat lamanya.
                ->disabled(fn (?Unit $record) => $record?->surat()->exists() ?? false)
                ->dehydrated()
                ->helperText(fn (?Unit $record) => ($record?->surat()->exists() ?? false)
                    ? 'Tidak bisa diubah: unit ini sudah dipakai di surat.'
                    : 'Unit milik satu perusahaan. Unit yang sama di perusahaan lain dibuat sebagai baris terpisah.'),
            TextInput::make('kode')->label('Kode Unit')->required()->maxLength(20)
                ->helperText('Muncul di nomor surat, mis. 2, 3A.'),
            TextInput::make('nama')->label('Nama Unit')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('perusahaan.kode_surat')->label('Perusahaan')->badge()->color('gray')->sortable(),
                TextColumn::make('kode')->badge()->searchable()->sortable(),
                TextColumn::make('nama')->searchable()->sortable(),
                TextColumn::make('surat_count')->label('Dipakai di')->suffix(' surat')->numeric()->sortable()
                    ->color(fn ($state) => $state > 0 ? 'primary' : 'gray'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->withCount('surat'))
            ->defaultSort('kode')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (Unit $record) => $record->surat_count > 0)
                    ->tooltip(fn (Unit $record) => $record->surat_count > 0
                        ? "Tidak bisa dihapus: dipakai di {$record->surat_count} surat"
                        : null),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUnits::route('/')];
    }
}
