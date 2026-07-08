<?php

namespace App\Filament\Resources\LMS\LmsCoursePlans;

use App\Filament\Resources\LMS\LmsCoursePlans\Pages\CreateLmsCoursePlan;
use App\Filament\Resources\LMS\LmsCoursePlans\Pages\EditLmsCoursePlan;
use App\Filament\Resources\LMS\LmsCoursePlans\Pages\ListLmsCoursePlans;
use App\Filament\Resources\LMS\LmsCoursePlans\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\LMS\LmsCoursePlans\Schemas\LmsCoursePlanForm;
use App\Filament\Resources\LMS\LmsCoursePlans\Tables\LmsCoursePlansTable;
use App\Models\LMS\LmsCoursePlans;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LmsCoursePlanResource extends Resource
{
    protected static ?string $model = LmsCoursePlans::class;

    protected static ?string $navigationLabel = "Rencana Pelatihan";

    protected static string | UnitEnum | null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function form(Schema $schema): Schema
    {
        return LmsCoursePlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LmsCoursePlansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLmsCoursePlans::route('/'),
            'create' => CreateLmsCoursePlan::route('/create'),
            'edit' => EditLmsCoursePlan::route('/{record}/edit'),
        ];
    }
}
