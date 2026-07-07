<?php

namespace App\Filament\Resources\Payroll\MasterDoctorServices\Pages;

use App\Filament\Resources\Payroll\MasterDoctorServices\MasterDoctorServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMasterDoctorService extends EditRecord
{
    protected static string $resource = MasterDoctorServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
