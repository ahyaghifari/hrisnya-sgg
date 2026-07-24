<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Insentif metode "master" jadi roster ala Koperasi (`user_koperasi`), bukan lagi log kejadian
 * per tanggal — `incentive_date` sekarang cuma keterangan "diterima sejak kapan", TIDAK dipakai
 * lagi buat filter periode payroll (lihat IncentiveCalculator). Baris aktif ikut setiap
 * Generate/Regenerate sampai dinonaktifkan lewat toggle di halaman Insentif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incentives', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('incentives', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
