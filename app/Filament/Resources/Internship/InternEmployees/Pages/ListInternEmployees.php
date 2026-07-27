<?php

namespace App\Filament\Resources\Internship\InternEmployees\Pages;

use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Internship\InternEmployees\InternEmployeeResource;

/**
 * Reuse penuh ListEmployees (termasuk import Excel) - cuma resource & judul yang beda, list-nya
 * sudah otomatis ter-scope ke intern lewat InternEmployeeResource::getEloquentQuery().
 */
class ListInternEmployees extends ListEmployees
{
    protected static string $resource = InternEmployeeResource::class;
    protected static ?string $title = 'Karyawan Magang';
}
