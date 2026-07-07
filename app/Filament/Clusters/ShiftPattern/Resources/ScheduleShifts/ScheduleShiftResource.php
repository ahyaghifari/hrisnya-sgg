<?php

namespace App\Filament\Clusters\ShiftPattern\Resources\ScheduleShifts;

use App\Filament\Clusters\ShiftPattern\Resources\ScheduleShifts\Pages\CreateScheduleShift;
use App\Filament\Clusters\ShiftPattern\Resources\ScheduleShifts\Pages\EditScheduleShift;
use App\Filament\Clusters\ShiftPattern\Resources\ScheduleShifts\Pages\ListScheduleShifts;
use App\Filament\Clusters\ShiftPattern\Resources\ScheduleShifts\Schemas\ScheduleShiftForm;
use App\Filament\Clusters\ShiftPattern\Resources\ScheduleShifts\Tables\ScheduleShiftsTable;
use App\Filament\Clusters\ShiftPattern\ShiftPatternCluster;
use App\Models\Shift\ShiftScheduleAssignments;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ScheduleShiftResource extends Resource
{
    protected static ?string $model = ShiftScheduleAssignments::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Jadwal Shift';

    protected static ?int $navigationSort = 3;

    protected static ?string $cluster = ShiftPatternCluster::class;

    protected static ?string $recordTitleAttribute = 'sc';

    public static function form(Schema $schema): Schema
    {
        return ScheduleShiftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScheduleShiftsTable::configure($table);
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
            'index' => ListScheduleShifts::route('/'),
            'create' => CreateScheduleShift::route('/create'),
            // 'edit' => EditScheduleShift::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return Gate::allows('view_jadwal_shift');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_jadwal_shift');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_jadwal_shift');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('edit_jadwal_shift');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete_jadwal_shift');
    }
}
