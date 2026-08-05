<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `lembur_metode` (module=payroll) sudah tidak dipakai kode manapun — LemburCalculator
 * cuma punya 1 metode (per Pangkat, lihat docblocknya: "Satu-satunya metode Lembur di sistem
 * ini"), tidak pernah dicek lewat Setting::resolve('lembur_metode', ...) di mana pun, dan
 * tidak diklaim GlobalPayrollSettingEnum/PayrollComponentEnum::requiredSettings() — baris
 * lama ini cuma "nyangkut" tampil di section "Pengaturan Lainnya" halaman Komponen Gaji tanpa
 * fungsi apa pun. Dihapus sekarang karena akan ada mekanisme lembur pengganti (dikonfirmasi
 * user 2026-08-05) - setting baru nanti pakai key sendiri, bukan pakai ulang key ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('module', 'payroll')
            ->where('key', 'lembur_metode')
            ->delete();
    }

    public function down(): void
    {
        // Data cleanup, bukan schema - kalau perlu balikin, isi manual lewat halaman
        // Komponen Gaji (tidak ada nilai baku yang bisa direkonstruksi otomatis).
    }
};
