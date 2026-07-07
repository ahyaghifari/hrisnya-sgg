<?php

namespace App\Filament\Resources\Master\EmployeeTypes;

use App\Filament\Resources\Master\EmployeeTypes\Pages\CreateEmployeeType;
use App\Filament\Resources\Master\EmployeeTypes\Pages\EditEmployeeType;
use App\Filament\Resources\Master\EmployeeTypes\Pages\ListEmployeeTypes;
use App\Filament\Resources\Master\EmployeeTypes\Schemas\EmployeeTypeForm;
use App\Filament\Resources\Master\EmployeeTypes\Tables\EmployeeTypesTable;
use App\Helpers\PermissionHelper;
use App\Models\Master\EmployeeTypes;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class EmployeeTypeResource extends Resource
{
    protected static ?string $model = EmployeeTypes::class;

    protected static ?string $navigationLabel = "Tipe Karyawan";

    protected static string | UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 9;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    public static function form(Schema $schema): Schema
    {
        return EmployeeTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeTypesTable::configure($table);
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
            'index' => ListEmployeeTypes::route('/'),
            // 'create' => CreateEmployeeType::route('/create'),
            // 'edit' => EditEmployeeType::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view_master_data_jenis_karyawan') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return Auth::user()?->can('view_master_data_jenis_karyawan') ?? false;
    }
    
    public static function canCreate(): bool
    {
        return Auth::user()?->can('create_master_data_jenis_karyawan') ?? false;
    }
    
    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->can('edit_master_data_jenis_karyawan') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->can('delete_master_data_jenis_karyawan') ?? false;
    }
}
