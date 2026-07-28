<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flexible tidak punya start_time resmi, tapi user tetap butuh batas jam masuk per hari (bisa
 * beda tiap hari, makanya ini per-baris ScheduleFlexibleDay, bukan schedule-level seperti
 * flexible_checkin_from/until). Nullable = tidak ada keterlambatan sama sekali untuk hari itu
 * (default) - kalau diisi mis. "10:00", check-in lewat dari jam itu dihitung telat, lihat
 * FlexibleScheduleCalculator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_flexible_days', function (Blueprint $table) {
            $table->time('late_checkin_deadline')->nullable()->after('min_work_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_flexible_days', function (Blueprint $table) {
            $table->dropColumn('late_checkin_deadline');
        });
    }
};
