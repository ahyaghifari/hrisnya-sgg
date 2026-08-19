<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Kepala Ruangan" per Schedule - dipakai TeamShiftScheduleCard buat resolve "Detail Jadwal"
 * menuju Schedule yang BENAR (dulu asal ambil Schedule pertama yang ketemu dari anggota tim,
 * ambigu kalau atasan punya bawahan di lebih dari 1 Schedule/unit). Nullable - jadwal lama
 * tanpa kepala ruangan tetap jalan seperti biasa (fallback ke logic lama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Bukan after('org_structure_id') - kolom itu sudah didrop lebih dulu
            // (2026_07_19_143657_drop_org_structure_id_from_schedules_table).
            $table->foreignUlid('head_user_id')->nullable()->after('color')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['head_user_id']);
            $table->dropColumn('head_user_id');
        });
    }
};
