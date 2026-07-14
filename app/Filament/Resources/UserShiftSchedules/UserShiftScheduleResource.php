<?php

namespace App\Filament\Resources\UserShiftSchedules;

use App\Filament\Resources\UserShiftSchedules\Pages\ListUserShiftSchedules;
use App\Models\Shift\ShiftScheduleAssignments;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class UserShiftScheduleResource extends Resource
{
    protected static ?string $model = ShiftScheduleAssignments::class;

    protected static ?string $navigationLabel = 'Jadwal Shift';

    protected static ?int $navigationSort = 4;

    protected static string | UnitEnum | null $navigationGroup = 'Karyawan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    public static function canAccess(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', Auth::id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserShiftSchedules::route('/'),
        ];
    }
}
