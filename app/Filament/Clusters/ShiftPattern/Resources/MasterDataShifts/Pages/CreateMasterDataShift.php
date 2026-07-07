<?php

namespace App\Filament\Clusters\ShiftPattern\Resources\MasterDataShifts\Pages;

use App\Filament\Clusters\ShiftPattern\Resources\MasterDataShifts\MasterDataShiftResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMasterDataShift extends CreateRecord
{
    protected static string $resource = MasterDataShiftResource::class;
    protected static ?string $title = 'Buat Shift Baru';
}
