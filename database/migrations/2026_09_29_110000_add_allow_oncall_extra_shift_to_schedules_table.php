<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oncall SENGAJA dikeluarkan dari extra_shift_rules company-wide (cuma Pagi/Siang/Malam yang
 * bisa dipasangkan di sana, lihat ExtraShiftRulePage::getShiftTypeOptionsProperty()) - tapi
 * beberapa unit MAU Oncall bisa jadi pilihan Extra Shift juga, bukan keputusan yang cocok
 * digeneralisir company-wide (unit lain mungkin justru tidak mau Oncall dobel jadi Extra Shift).
 * Jadi saklarnya per Schedule di sini, bukan baris baru di extra_shift_rules (instruksi user
 * 2026-09-29). Default FALSE - perilaku existing (Oncall tidak pernah muncul di pilihan Extra
 * Shift) tidak berubah untuk jadwal yang sudah ada sampai kepala ruangan/admin menyalakannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->boolean('allow_oncall_extra_shift')->default(false)->after('use_teams');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('allow_oncall_extra_shift');
        });
    }
};
