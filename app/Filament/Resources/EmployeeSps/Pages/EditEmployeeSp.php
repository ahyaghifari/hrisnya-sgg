<?php

namespace App\Filament\Resources\EmployeeSps\Pages;

use App\Filament\Resources\EmployeeSps\EmployeeSpResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Read-only — SP resmi tidak diedit lewat form (planning/2026-09-23-sp-dan-merit-point.md §2.1),
 * halaman ini cuma menampilkan isi SP (infolist di EmployeeSpForm::editSchema()).
 */
class EditEmployeeSp extends EditRecord
{
    protected static string $resource = EmployeeSpResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getFormActions(): array
    {
        return [];
    }
}
