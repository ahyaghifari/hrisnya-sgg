<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warna sel Libur (off_day) di tab "Detail Penjadwalan" sebelumnya hardcode di blade
 * (color-mix merah + teks putih) - user 2026-09-14 minta bisa diatur, bukan tetap. Nullable:
 * kosong = pakai default lama (fallback di blade), tidak mengubah tampilan jadwal existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('off_bg_color')->nullable()->after('color');
            $table->string('off_font_color')->nullable()->after('off_bg_color');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['off_bg_color', 'off_font_color']);
        });
    }
};
