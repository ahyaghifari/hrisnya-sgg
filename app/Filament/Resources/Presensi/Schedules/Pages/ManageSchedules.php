<?php

namespace App\Filament\Resources\Presensi\Schedules\Pages;

use App\Filament\Resources\Presensi\Schedules\ScheduleResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSchedules extends ManageRecords
{
    protected static string $resource = ScheduleResource::class;

    protected static ?string $title = 'Jadwal';

    /** Sengaja TANPA CreateAction — bikin jadwal baru cuma lewat wizard portal
     * (/presensi/jadwal/tambah), lihat komentar ScheduleResource. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
