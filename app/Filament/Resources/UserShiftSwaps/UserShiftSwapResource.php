<?php

namespace App\Filament\Resources\UserShiftSwaps;

use App\Filament\Resources\UserShiftSwaps\Pages\CreateUserShiftSwap;
use App\Filament\Resources\UserShiftSwaps\Pages\EditUserShiftSwap;
use App\Filament\Resources\UserShiftSwaps\Pages\ListUserShiftSwaps;
use App\Filament\Resources\UserShiftSwaps\Schemas\UserShiftSwapForm;
use App\Filament\Resources\UserShiftSwaps\Tables\UserShiftSwapsTable;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\UserShiftSwap;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class UserShiftSwapResource extends Resource
{
    protected static ?string $model = ShiftSwapRequests::class;

    protected static ?string $navigationLabel = "Tukar Shift";

    protected static ?string $navigationParentItem = 'Jadwal Shift';

    protected static string | UnitEnum | null $navigationGroup = 'Jadwal Shift Karyawan';

    public static function form(Schema $schema): Schema
    {
        return UserShiftSwapForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserShiftSwapsTable::configure($table);
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
            'index' => ListUserShiftSwaps::route('/'),
            'create' => CreateUserShiftSwap::route('/create'),
            'edit' => EditUserShiftSwap::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return Gate::allows('view_manajemen_tukar_shift');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_manajemen_tukar_shift');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_manajemen_tukar_shift');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('edit_manajemen_tukar_shift');
    }
}
