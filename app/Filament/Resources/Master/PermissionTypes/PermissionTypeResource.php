<?php

namespace App\Filament\Resources\Master\PermissionTypes;

use App\Filament\Resources\Master\PermissionTypes\Pages\CreatePermissionType;
use App\Filament\Resources\Master\PermissionTypes\Pages\EditPermissionType;
use App\Filament\Resources\Master\PermissionTypes\Pages\ListPermissionTypes;
use App\Filament\Resources\Master\PermissionTypes\Schemas\PermissionTypeForm;
use App\Filament\Resources\Master\PermissionTypes\Tables\PermissionTypesTable;
use App\Models\Master\Attendance\PermissionTypes;
use App\Models\Master\PermissionType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class PermissionTypeResource extends Resource
{
    protected static ?string $model = PermissionTypes::class;

    protected static ?string $navigationLabel = "Jenis Izin";

    protected static string | UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 12;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function form(Schema $schema): Schema
    {
        return PermissionTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PermissionTypesTable::configure($table);
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
            'index' => ListPermissionTypes::route('/'),
            // 'create' => CreatePermissionType::route('/create'),
            // 'edit' => EditPermissionType::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return Gate::allows('view_master_data_jenis_perizinan');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_master_data_jenis_perizinan');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_master_data_jenis_perizinan');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('edit_master_data_jenis_perizinan');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete_master_data_jenis_perizinan');
    }
}
