<?php

namespace App\Filament\Resources\Master\Departments;

use App\Enum\OrgStructurLevel;
use App\Filament\Resources\Master\Departments\Pages\CreateDepartments;
use App\Filament\Resources\Master\Departments\Pages\EditDepartments;
use App\Filament\Resources\Master\Departments\Pages\ListDepartments;
use App\Filament\Resources\Master\Departments\Schemas\DepartmentsForm;
use App\Filament\Resources\Master\Departments\Tables\DepartmentsTable;
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

class DepartmentsResource extends Resource
{
    protected static ?string $model = OrgStructure::class;

    protected static ?string $navigationLabel = "Departemen";

    protected static string | UnitEnum | null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $recordTitleAttribute = 'Master/Departments';

    public static function form(Schema $schema): Schema
    {
        return DepartmentsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepartmentsTable::configure($table);
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
            'index' => ListDepartments::route('/'),
            'create' => CreateDepartments::route('/create'),
            'edit' => EditDepartments::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id)
                ->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value);
        }

        // Add default filter for level == 1
        $query->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value);

        return $query;
    }
}
