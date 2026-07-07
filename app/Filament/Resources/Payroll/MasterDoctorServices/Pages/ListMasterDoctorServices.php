<?php

namespace App\Filament\Resources\Payroll\MasterDoctorServices\Pages;

use App\Filament\Resources\Payroll\MasterDoctorServices\MasterDoctorServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMasterDoctorServices extends ListRecords
{
    protected static string $resource = MasterDoctorServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->modalWidth('md')
                ->modalHeading('Tambah Jasa Dokter'),
        ];
    }
}
