<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Karyawan boleh meminta HC yang mengisikan klarifikasinya (instruksi user 2026-10-02).
 * true = karyawan memilih diisikan HC; HC lalu mengisi `subject_clarification` atas namanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_reports', function (Blueprint $table) {
            $table->boolean('klarifikasi_diisi_hc')->default(false)->after('klarifikasi_diajukan_at');
        });
    }

    public function down(): void
    {
        Schema::table('sp_reports', function (Blueprint $table) {
            $table->dropColumn('klarifikasi_diisi_hc');
        });
    }
};
