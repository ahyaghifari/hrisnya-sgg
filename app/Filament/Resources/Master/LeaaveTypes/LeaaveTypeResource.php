<?php

namespace App\Filament\Resources\Master\LeaaveTypes;

use App\Filament\Resources\Master\LeaaveTypes\Pages\CreateLeaaveType;
use App\Filament\Resources\Master\LeaaveTypes\Pages\EditLeaaveType;
use App\Filament\Resources\Master\LeaaveTypes\Pages\ListLeaaveTypes;
use App\Filament\Resources\Master\LeaaveTypes\Schemas\LeaaveTypeForm;
use App\Filament\Resources\Master\LeaaveTypes\Tables\LeaaveTypesTable;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\LeaaveType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class LeaaveTypeResource extends Resource
{
    protected static ?string $model = LeaveTypes::class;

    protected static ?string $navigationLabel = "Jenis Cuti";

    protected static string | UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 11;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function form(Schema $schema): Schema
    {
        return LeaaveTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeaaveTypesTable::configure($table);
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
            'index' => ListLeaaveTypes::route('/'),
            // 'create' => CreateLeaaveType::route('/create'),
            // 'edit' => EditLeaaveType::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return Gate::allows('view_master_data_jenis_cuti');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_master_data_jenis_cuti');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_master_data_jenis_cuti');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('edit_master_data_jenis_cuti');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete_master_data_jenis_cuti');
    }
}
