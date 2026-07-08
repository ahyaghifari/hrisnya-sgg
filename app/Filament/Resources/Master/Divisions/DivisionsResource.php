<?php

namespace App\Filament\Resources\Master\Divisions;

use App\Enum\OrgStructurLevel;
use App\Filament\Resources\Master\Divisions\Pages\CreateDivisions;
use App\Filament\Resources\Master\Divisions\Pages\EditDivisions;
use App\Filament\Resources\Master\Divisions\Pages\ListDivisions;
use App\Filament\Resources\Master\Divisions\Schemas\DivisionsForm;
use App\Filament\Resources\Master\Divisions\Tables\DivisionsTable;
use App\Helpers\PermissionHelper;
use App\Models\Master\OrgStructure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DivisionsResource extends Resource
{
    protected static ?string $model = OrgStructure::class;

    protected static ?string $navigationLabel = "Divisi";

    protected static string | UnitEnum | null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    public static function form(Schema $schema): Schema
    {
        return DivisionsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DivisionsTable::configure($table);
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
            'index' => ListDivisions::route('/'),
            'create' => CreateDivisions::route('/create'),
            'edit' => EditDivisions::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id)
                ->where('level', OrgStructurLevel::LEVEL_DIVISION->value);
        }

        $query->where('level', OrgStructurLevel::LEVEL_DIVISION->value);

        return $query;
    }
}
