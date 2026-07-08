<?php

namespace App\Filament\Resources\LMS\LmsBaselines;

use App\Filament\Resources\LMS\LmsBaselines\Pages\CreateLmsBaselines;
use App\Filament\Resources\LMS\LmsBaselines\Pages\EditLmsBaselines;
use App\Filament\Resources\LMS\LmsBaselines\Pages\ListLmsBaselines;
use App\Filament\Resources\LMS\LmsBaselines\Schemas\LmsBaselinesForm;
use App\Filament\Resources\LMS\LmsBaselines\Tables\LmsBaselinesTable;
use App\Models\LMS\LmsCourseBaselines;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class LmsBaselinesResource extends Resource
{
    protected static ?string $model = LmsCourseBaselines::class;

    protected static ?string $navigationLabel = "Baseline Pelatihan";

    protected static ?string $navigationParentItem = 'Pelatihan';

    protected static string | UnitEnum | null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return LmsBaselinesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LmsBaselinesTable::configure($table);
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
            'index' => ListLmsBaselines::route('/'),
            'create' => CreateLmsBaselines::route('/create'),
            'edit' => EditLmsBaselines::route('/{record}/edit'),
        ];
    }
}
