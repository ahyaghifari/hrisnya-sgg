<?php

namespace App\Filament\Resources\Assessment\AssessmentTemplates;

use App\Filament\Resources\Assessment\AssessmentTemplates\Pages\CreateAssessmentTemplate;
use App\Filament\Resources\Assessment\AssessmentTemplates\Pages\EditAssessmentTemplate;
use App\Filament\Resources\Assessment\AssessmentTemplates\Pages\ListAssessmentTemplates;
use App\Filament\Resources\Assessment\AssessmentTemplates\RelationManagers\TemplateItemsRelationManager;
use App\Filament\Resources\Assessment\AssessmentTemplates\Schemas\AssessmentTemplateForm;
use App\Filament\Resources\Assessment\AssessmentTemplates\Tables\AssessmentTemplatesTable;
use App\Models\Assessment\AssessmentTemplates;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AssessmentTemplateResource extends Resource
{
    protected static ?string $model = AssessmentTemplates::class;

    protected static ?string $navigationLabel = 'Form Template';

    protected static ?int $navigationSort = 2;


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return AssessmentTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssessmentTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TemplateItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssessmentTemplates::route('/'),
            'create' => CreateAssessmentTemplate::route('/create'),
            'edit' => EditAssessmentTemplate::route('/{record}/edit'),
        ];
    }
}
