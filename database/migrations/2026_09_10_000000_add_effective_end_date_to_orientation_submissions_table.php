<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal efektif berakhirnya hubungan kerja saat keputusan penilai orientasi = "tidak
 * dilanjutkan" (diisi HR pada langkah tindak lanjut). Menggantikan pemakaian `extended_until`
 * yang dulu untuk keputusan "perpanjang orientasi" (opsi itu dihapus 2026-09-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orientation_submissions', function (Blueprint $table) {
            $table->date('effective_end_date')->nullable()->after('extended_until');
        });
    }

    public function down(): void
    {
        Schema::table('orientation_submissions', function (Blueprint $table) {
            $table->dropColumn('effective_end_date');
        });
    }
};
