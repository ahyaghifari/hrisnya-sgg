<?php

namespace App\Filament\Dokter\Resources\Specialties;

use App\Filament\Dokter\Resources\Specialties\Pages\ManageSpecialties;
use App\Models\Doctor\Specialty;
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

class SpecialtyResource extends Resource
{
    protected static ?string $model = Specialty::class;

    protected static ?string $navigationLabel = 'Spesialisasi';

    protected static ?string $modelLabel = 'Spesialisasi';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->label('Nama')->required()->maxLength(150)->unique(ignoreRecord: true),
            TextInput::make('kode')->label('Kode')->maxLength(50),
            Select::make('status')->label('Status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])->default('aktif')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('kode')->label('Kode')->placeholder('-'),
                TextColumn::make('doctors_count')->label('Dokter')->counts('doctors'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'aktif' ? 'Aktif' : 'Nonaktif')
                    ->color(fn (string $state) => $state === 'aktif' ? 'success' : 'gray'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSpecialties::route('/')];
    }
}
