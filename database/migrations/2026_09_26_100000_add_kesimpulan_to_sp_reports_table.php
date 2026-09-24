<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kesimpulan dipisah dari Analisis (instruksi user 2026-09-24) - beda hal: analisis = pembahasan
 * temuan, kesimpulan = ringkasan keputusan akhir dari Berita Acara.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_reports', function (Blueprint $table) {
            $table->text('kesimpulan')->nullable()->after('analysis');
        });
    }

    public function down(): void
    {
        Schema::table('sp_reports', function (Blueprint $table) {
            $table->dropColumn('kesimpulan');
        });
    }
};
