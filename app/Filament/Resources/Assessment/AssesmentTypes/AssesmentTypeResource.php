<?php

namespace App\Filament\Resources\Assessment\AssesmentTypes;

use App\Filament\Resources\Assessment\AssesmentTypes\Pages\CreateAssesmentType;
use App\Filament\Resources\Assessment\AssesmentTypes\Pages\EditAssesmentType;
use App\Filament\Resources\Assessment\AssesmentTypes\Pages\ListAssesmentTypes;
use App\Filament\Resources\Assessment\AssesmentTypes\Schemas\AssesmentTypeForm;
use App\Filament\Resources\Assessment\AssesmentTypes\Tables\AssesmentTypesTable;
use App\Filament\Clusters\Assessment\AssessmentCluster;
use App\Models\Assessment\AssessmentTypes;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AssesmentTypeResource extends Resource
{
    protected static ?string $model = AssessmentTypes::class;

    protected static ?string $cluster = AssessmentCluster::class;

    protected static ?string $navigationLabel = 'Tipe Penilaian';

    protected static ?int $navigationSort = 1;


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return AssesmentTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssesmentTypesTable::configure($table);
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
            'index' => ListAssesmentTypes::route('/'),
            // 'create' => CreateAssesmentType::route('/create'),
            // 'edit' => EditAssesmentType::route('/{record}/edit'),
        ];
    }
}
