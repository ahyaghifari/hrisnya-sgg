<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 7 baris (Senin-Minggu) per `schedules` tipe FLEXIBLE. Dibuat kosong, sama seperti fixed_days. */
    public function up(): void
    {
        Schema::create('schedule_flexible_days', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_off_day')->default(false);
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedSmallInteger('min_work_minutes')->default(0);
            $table->unsignedSmallInteger('max_work_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_break_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_min_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_max_minutes')->default(0);
            // Pengaturan Lanjutan (§2.5) — checkin/checkout buffer & late/early tolerance
            // disediakan kolomnya biar 1 struktur konsisten, meski kurang relevan buat Flexible
            // (jendela absensinya sendiri ada di schedules.flexible_checkin_from/until).
            $table->unsignedSmallInteger('checkin_buffer_minutes')->default(0);
            $table->unsignedSmallInteger('checkout_buffer_minutes')->default(0);
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_tolerance_minutes')->default(0);
            $table->unsignedTinyInteger('counted_work_days')->default(1);
            $table->unsignedSmallInteger('max_work_minutes_cap')->nullable();
            $table->unsignedSmallInteger('half_day_threshold_minutes')->default(0);
            $table->unsignedSmallInteger('absent_threshold_minutes')->default(0);
            $table->timestamps();
            $table->unique(['schedule_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_flexible_days');
    }
};
