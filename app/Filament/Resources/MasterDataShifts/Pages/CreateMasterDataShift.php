<?php

namespace App\Filament\Resources\MasterDataShifts\Pages;

use App\Filament\Resources\MasterDataShifts\MasterDataShiftResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMasterDataShift extends CreateRecord
{
    protected static string $resource = MasterDataShiftResource::class;
    protected static ?string $title = 'Buat Shift Baru';
}
