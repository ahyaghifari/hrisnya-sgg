<?php

namespace App\Filament\Resources\UserShiftSchedules\Pages;

use App\Filament\Resources\UserShiftSchedules\UserShiftScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserShiftSchedules extends ListRecords
{
    protected static string $resource = UserShiftScheduleResource::class;

    protected static ?string $title = 'Jadwal Shift Saya';
}
