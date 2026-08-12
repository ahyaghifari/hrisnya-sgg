<?php

namespace App\Filament\Resources\ExecutiveEmployees\Pages;

use App\Filament\Resources\ExecutiveEmployees\ExecutiveEmployeeResource;
use Filament\Resources\Pages\ListRecords;

class ListExecutiveEmployees extends ListRecords
{
    protected static string $resource = ExecutiveEmployeeResource::class;

    protected static ?string $title = 'Data Eksekutif';

    protected function getHeaderActions(): array
    {
        // Sengaja kosong - tidak ada Create/Import, 2 akun ini fixed (lihat
        // User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS), cuma bisa diedit.
        return [];
    }
}
