<?php

namespace App\Filament\Resources\Competency\PositionCompetencies;

use App\Filament\Resources\Competency\PositionCompetencies\Pages\CreatePositionCompetency;
use App\Filament\Resources\Competency\PositionCompetencies\Pages\EditPositionCompetency;
use App\Filament\Resources\Competency\PositionCompetencies\Pages\ListPositionCompetencies;
use App\Filament\Resources\Competency\PositionCompetencies\Schemas\PositionCompetencyForm;
use App\Filament\Resources\Competency\PositionCompetencies\Tables\PositionCompetenciesTable;
use App\Models\Master\JobPositionCompetencies;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class PositionCompetencyResource extends Resource
{
    protected static ?string $model = \App\Models\Master\JobPositions::class;
    protected static ?string $navigationLabel = 'Jabatan & Kompetensi';
    protected static string | UnitEnum | null $navigationGroup = 'LMS';
    protected static ?int $navigationSort = 3;
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

    public static function canAccess(): bool
    {
        return Gate::allows('view_jabatan_dan_kompetensi');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_jabatan_dan_kompetensi');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_jabatan_dan_kompetensi');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('edit_jabatan_dan_kompetensi');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete_jabatan_dan_kompetensi');
    }
}
