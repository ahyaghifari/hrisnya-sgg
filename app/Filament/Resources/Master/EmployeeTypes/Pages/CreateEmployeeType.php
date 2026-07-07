<?php

namespace App\Filament\Resources\Master\EmployeeTypes\Pages;

use App\Filament\Resources\Master\EmployeeTypes\EmployeeTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeType extends CreateRecord
{
    protected static string $resource = EmployeeTypeResource::class;
    protected static ?string $title = 'Tambah Tipe Karyawan';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
