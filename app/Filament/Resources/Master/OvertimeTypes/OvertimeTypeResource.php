<?php

namespace App\Filament\Resources\Master\OvertimeTypes;

use App\Filament\Resources\Master\OvertimeTypes\Pages\CreateOvertimeType;
use App\Filament\Resources\Master\OvertimeTypes\Pages\EditOvertimeType;
use App\Filament\Resources\Master\OvertimeTypes\Pages\ListOvertimeTypes;
use App\Filament\Resources\Master\OvertimeTypes\Schemas\OvertimeTypeForm;
use App\Filament\Resources\Master\OvertimeTypes\Tables\OvertimeTypesTable;
use App\Models\Master\Attendance\OvertimeTypes;
use App\Models\Master\OvertimeType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class OvertimeTypeResource extends Resource
{
    protected static ?string $model = OvertimeTypes::class;

    protected static ?string $navigationLabel = "Jenis Lembur";

    protected static string | UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 13;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function form(Schema $schema): Schema
    {
        return OvertimeTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OvertimeTypesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOvertimeTypes::route('/'),
            // 'create' => CreateOvertimeType::route('/create'),
            // 'edit' => EditOvertimeType::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return Gate::allows('view_master_data_jenis_lembur');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_master_data_jenis_lembur');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_master_data_jenis_lembur');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('edit_master_data_jenis_lembur');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete_master_data_jenis_lembur');
    }
}
