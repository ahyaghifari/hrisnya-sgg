<?php

namespace App\Filament\Resources\Master\SubDivisions;

use App\Enum\OrgStructurLevel;
use App\Filament\Resources\Master\SubDivisions\Pages\CreateSubDivisions;
use App\Filament\Resources\Master\SubDivisions\Pages\EditSubDivisions;
use App\Filament\Resources\Master\SubDivisions\Pages\ListSubDivisions;
use App\Filament\Resources\Master\SubDivisions\Schemas\SubDivisionsForm;
use App\Filament\Resources\Master\SubDivisions\Tables\SubDivisionsTable;
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

class SubDivisionsResource extends Resource
{
    protected static ?string $model = OrgStructure::class;

    protected static ?string $navigationLabel = "Sub Divisi";

    protected static string | UnitEnum | null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    public static function form(Schema $schema): Schema
    {
        return SubDivisionsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubDivisionsTable::configure($table);
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
            'index' => ListSubDivisions::route('/'),
            'create' => CreateSubDivisions::route('/create'),
            'edit' => EditSubDivisions::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id)
                ->where('level', OrgStructurLevel::LEVEL_SUBDIVISION->value);
        }

        $query->where('level', OrgStructurLevel::LEVEL_SUBDIVISION->value);

        return $query;
    }
}
