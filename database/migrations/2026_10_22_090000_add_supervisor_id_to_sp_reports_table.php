<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atasan langsung karyawan ikut menandatangani Berita Acara (instruksi user 2026-10-06), setelah
 * "Mengetahui" dan sebelum karyawan. Dibekukan saat BA dikirim untuk TTD; NULL = tidak ada atasan
 * atau atasannya sama dengan pembuat/mengetahui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_reports', function (Blueprint $table) {
            $table->foreignUlid('supervisor_id')->nullable()->after('knows_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sp_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
        });
    }
};
