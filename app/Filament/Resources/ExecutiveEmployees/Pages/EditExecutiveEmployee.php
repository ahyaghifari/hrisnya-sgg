<?php

namespace App\Filament\Resources\ExecutiveEmployees\Pages;

use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\ExecutiveEmployees\ExecutiveEmployeeResource;

/**
 * Extends EditEmployee (bukan cuma EditRecord) buat reuse afterSave() apa adanya - sync
 * role_ids & tracking job position aktif, behaviornya sama seperti edit Karyawan biasa
 * (2026-08-12). Cuma $resource & header actions yang beda (tidak ada Hapus - 2 akun ini
 * fixed, tidak boleh dihapus dari sini).
 */
class EditExecutiveEmployee extends EditEmployee
{
    protected static string $resource = ExecutiveEmployeeResource::class;

    protected static ?string $title = 'Edit Data Eksekutif';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
