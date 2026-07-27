<?php

namespace App\Filament\Resources\Internship\InternEmployees\Pages;

use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Internship\InternEmployees\InternEmployeeResource;

/**
 * Reuse penuh EditEmployee (sync roles, update UserJobPositions) - cuma resource & judul beda.
 */
class EditInternEmployee extends EditEmployee
{
    protected static string $resource = InternEmployeeResource::class;
    protected static ?string $title = 'Edit Karyawan Magang';
}
