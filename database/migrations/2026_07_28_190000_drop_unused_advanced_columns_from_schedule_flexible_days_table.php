<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modal "Pengaturan Lanjutan" Flexible disederhanakan jadi 2 field saja (Dihitung Kerja +
 * Setengah Hari), niru KONFIGURASI JADWAL FLEXIBLE.png - 6 kolom lain sudah tidak dipakai
 * FlexibleScheduleCalculator sama sekali (checkin/checkout buffer & late/early tolerance memang
 * tidak pernah dibaca sejak awal, max_work_minutes_cap & absent_threshold_minutes ikut dilepas
 * atas keputusan eksplisit user), jadi kolomnya ikut dihapus dari tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_flexible_days', function (Blueprint $table) {
            $table->dropColumn([
                'checkin_buffer_minutes',
                'checkout_buffer_minutes',
                'late_tolerance_minutes',
                'early_leave_tolerance_minutes',
                'max_work_minutes_cap',
                'absent_threshold_minutes',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('schedule_flexible_days', function (Blueprint $table) {
            $table->unsignedSmallInteger('checkin_buffer_minutes')->default(0)->after('overtime_max_minutes');
            $table->unsignedSmallInteger('checkout_buffer_minutes')->default(0)->after('checkin_buffer_minutes');
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(0)->after('checkout_buffer_minutes');
            $table->unsignedSmallInteger('early_leave_tolerance_minutes')->default(0)->after('late_tolerance_minutes');
            $table->unsignedSmallInteger('max_work_minutes_cap')->nullable()->after('counted_work_days');
            $table->unsignedSmallInteger('absent_threshold_minutes')->default(0)->after('half_day_threshold_minutes');
        });
    }
};
