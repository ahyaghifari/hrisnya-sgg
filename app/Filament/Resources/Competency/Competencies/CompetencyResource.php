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
use UnitEnum;

class CompetencyResource extends Resource
{
    protected static ?string $model = Competencies::class;
    protected static ?string $navigationLabel = 'Kompetensi';
    protected static ?int $navigationSort = 15;
    protected static string | UnitEnum | null $navigationGroup = 'Master';

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
}
