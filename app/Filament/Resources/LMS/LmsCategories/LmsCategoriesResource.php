<?php

namespace App\Filament\Resources\LMS\LmsCategories;

use App\Filament\Resources\LMS\LmsCategories\Pages\CreateLmsCategories;
use App\Filament\Resources\LMS\LmsCategories\Pages\EditLmsCategories;
use App\Filament\Resources\LMS\LmsCategories\Pages\ListLmsCategories;
use App\Filament\Resources\LMS\LmsCategories\Schemas\LmsCategoriesForm;
use App\Filament\Resources\LMS\LmsCategories\Tables\LmsCategoriesTable;
use App\Models\LMS\LmsCategories;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class LmsCategoriesResource extends Resource
{
    protected static ?string $model = LmsCategories::class;

    protected static ?string $navigationLabel = "Kategori";

    protected static ?string $navigationParentItem = 'Pelatihan';

    protected static string | UnitEnum | null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function form(Schema $schema): Schema
    {
        return LmsCategoriesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LmsCategoriesTable::configure($table);
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
            'index' => ListLmsCategories::route('/'),
            // 'create' => CreateLmsCategories::route('/create'),
            // 'edit' => EditLmsCategories::route('/{record}/edit'),
        ];
    }
}
