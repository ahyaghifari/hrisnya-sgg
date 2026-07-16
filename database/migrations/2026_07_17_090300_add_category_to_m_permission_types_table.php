<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `category` dipakai PayrollService cari pengajuan izin subtipe tertentu tanpa cocok
     * nama (fragile — pelajaran dari bug lama SalaryComponentNameEnum). Nullable, backfill
     * OTOMATIS cuma untuk 4 baris yang datanya sudah terkonfirmasi (Holding, dari
     * analisis-payroll-4-pembacaan-excel-skema-aktual.md). Company lain dibiarkan NULL —
     * kalau nanti mereka punya jenis izin sendiri, isi manual lewat admin.
     */
    public function up(): void
    {
        Schema::table('m_permission_types', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
        });

        $map = [
            'Izin Terlambat' => 'izin_terlambat_masuk',
            'Izin Pulang Lebih Awal' => 'izin_pulang_cepat',
            'Izin Tidak Masuk' => 'izin_seharian',
            'Izin Meninggalkan Jam Kerja' => 'izin_meninggalkan_jam_kerja',
        ];

        foreach ($map as $name => $category) {
            DB::table('m_permission_types')->where('name', $name)->update(['category' => $category]);
        }
    }

    public function down(): void
    {
        Schema::table('m_permission_types', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
