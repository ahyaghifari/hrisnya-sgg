<?php

namespace App\Filament\Resources\Assessment\AssessmentTemplates;

use App\Filament\Clusters\Assessment\AssessmentCluster;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class AssessmentTemplateResource extends Resource
{
    protected static ?string $model = AssessmentTemplates::class;

    protected static ?string $navigationLabel = 'Form Template';

    protected static ?int $navigationSort = 2;

    protected static ?string $cluster = AssessmentCluster::class;

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

    public static function canAccess(): bool
    {
        return Gate::allows('view_penilaian_karyawan');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_penilaian_karyawan');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_penilaian_karyawan');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('edit_penilaian_karyawan');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete_penilaian_karyawan');
    }
}
