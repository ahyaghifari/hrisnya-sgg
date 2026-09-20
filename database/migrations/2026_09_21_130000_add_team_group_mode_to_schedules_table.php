<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cara tim ditampilkan di tabel Detail Penjadwalan (& export Excel): 'row' = baris pemisah per tim,
 * 'column' = kolom Tim di samping nama. Dulu preferensi session per browser, sekarang pengaturan
 * per jadwal yang disimpan di DB (2026-09-21) - berlaku sama untuk semua yang membuka jadwal ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('team_group_mode', 10)->default('row')->after('use_teams');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('team_group_mode');
        });
    }
};
