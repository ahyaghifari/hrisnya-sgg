<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sama konsepnya dengan schedule_flexible_days.min_work_minutes, tapi efeknya beda: Flexible
 * cuma menandai meets_target=false (tetap tersimpan), Fixed di sini benar2 MENOLAK scan checkout
 * kalau durasi kerja belum memenuhi minimum - check_out_time attendance_records tidak ikut
 * terisi dari scan itu, nunggu scan checkout berikutnya yang bikin durasinya cukup (lihat
 * FixedScheduleCalculator). Default 0 = nonaktif (perilaku lama, semua schedule existing tidak
 * ikut berubah kecuali diisi manual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_fixed_days', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_work_minutes')->default(0)->after('break_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_fixed_days', function (Blueprint $table) {
            $table->dropColumn('min_work_minutes');
        });
    }
};
