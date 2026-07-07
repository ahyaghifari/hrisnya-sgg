<?php

namespace App\Filament\Resources\Competency\Competencies;

use App\Filament\Resources\Competency\Competencies\Pages\CreateCompetency;
use App\Filament\Resources\Competency\Competencies\Pages\EditCompetency;
use App\Filament\Resources\Competency\Competencies\Pages\ListCompetencies;
use App\Filament\Resources\Competency\Competencies\Schemas\CompetencyForm;
use App\Filament\Resources\Competency\Competencies\Tables\CompetenciesTable;
use App\Models\Competency\Competency;
use App\Models\Master\Competencies;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class CompetencyResource extends Resource
{
    protected static ?string $model = Competencies::class;
    protected static ?string $navigationLabel = 'Kompetensi';
    protected static ?string $navigationParentItem = 'Jabatan & Kompetensi';
    protected static ?int $navigationSort = 2;
    protected static string | UnitEnum | null $navigationGroup = 'LMS';

    public static function form(Schema $schema): Schema
    {
        return CompetencyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompetenciesTable::configure($table);
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
            'index' => ListCompetencies::route('/'),
            // 'create' => CreateCompetency::route('/create'),
            // 'edit' => EditCompetency::route('/{record}/edit'),
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
