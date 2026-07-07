<?php

namespace App\Filament\Resources\UserShiftSchedules\Pages;

use App\Filament\Resources\UserShiftSchedules\UserShiftScheduleResource;
use App\Filament\Widgets\ShiftCalendarWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserShiftSchedules extends ListRecords
{
    protected static string $resource = UserShiftScheduleResource::class;

    protected static ?string $title = 'Jadwal Shift Saya';

    protected function getHeaderWidgets(): array
    {
        return [
            ShiftCalendarWidget::class,
        ];
    }
}
