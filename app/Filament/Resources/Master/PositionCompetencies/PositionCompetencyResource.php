<?php

namespace App\Filament\Resources\Master\PositionCompetencies;

use App\Filament\Resources\Master\PositionCompetencies\Pages\ListPositionCompetencies;
use App\Filament\Resources\Master\PositionCompetencies\Tables\PositionCompetenciesTable;
use App\Models\Master\JobPositions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PositionCompetencyResource extends Resource
{
    protected static ?string $model = JobPositions::class;

    protected static ?string $navigationLabel = 'Jabatan & Kompetensi';
    protected static string | UnitEnum | null $navigationGroup = 'Master';
    protected static ?int $navigationSort = 16;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return PositionCompetenciesTable::configure($table);
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
            'index' => ListPositionCompetencies::route('/'),
        ];
    }
}
