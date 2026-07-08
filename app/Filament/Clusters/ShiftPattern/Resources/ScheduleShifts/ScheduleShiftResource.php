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
}
