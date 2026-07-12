<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-fix katalog `salary_components` (nama baru, lihat migration sebelumnya):
 * - "Leave" -> "Cuti": ini komponen Cuti (SalaryComponentNameEnum::CUTI), namanya kepeleset
 *   bahasa Inggris sejak awal, dirapikan biar konsisten dengan baris lain (Izin, Keterlambatan, dst).
 * - Baris "Lembur" dihapus: tidak pernah dipakai (PayrollService generate baris lembur langsung
 *   dari OvertimeRateRules, tanpa lookup ke katalog ini), dikonfirmasi 0 baris assignment yang
 *   mereferensikannya (aman dari FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('salary_components')->where('name', 'Leave')->update(['name' => 'Cuti']);
        DB::table('salary_components')->where('name', 'Lembur')->delete();
    }

    public function down(): void
    {
        DB::table('salary_components')->where('name', 'Cuti')->update(['name' => 'Leave']);
        // Baris "Lembur" yang dihapus sengaja tidak dikembalikan (data yatim, tidak ada nilai untuk direstore).
    }
};
