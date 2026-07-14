<?php

namespace App\Filament\Resources\ShiftTemplates;

use App\Filament\Clusters\ShiftPattern\ShiftPatternCluster;
use App\Filament\Resources\ShiftTemplates\Pages\CreateShiftTemplate;
use App\Filament\Resources\ShiftTemplates\Pages\EditShiftTemplate;
use App\Filament\Resources\ShiftTemplates\Pages\GenerateScheduleFromTemplate;
use App\Filament\Resources\ShiftTemplates\Pages\ListShiftTemplates;
use App\Filament\Resources\ShiftTemplates\Schemas\ShiftTemplateForm;
use App\Filament\Resources\ShiftTemplates\Tables\ShiftTemplatesTable;
use App\Models\Shift\ShiftTemplates;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShiftTemplateResource extends Resource
{
    protected static ?string $model = ShiftTemplates::class;

    protected static ?string $cluster = ShiftPatternCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Template Shift';

    protected static ?int $navigationSort = 2;


    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ShiftTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShiftTemplatesTable::configure($table);
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
            'index'    => ListShiftTemplates::route('/'),
            'create'   => CreateShiftTemplate::route('/create'),
            'edit'     => EditShiftTemplate::route('/{record}/edit'),
            'generate' => GenerateScheduleFromTemplate::route('/{record}/generate'),
        ];
    }
}
