<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 7 baris (Senin-Minggu) per `schedules` tipe FIXED. Belum ada data existing yang
     * di-backfill ke sini — sistem lama (`m_shifts`) tidak punya konsep jadwal per-hari sama
     * sekali, jadi tabel ini dibuat kosong, diisi manual lewat UI (Tahap 2).
     */
    public function up(): void
    {
        Schema::create('schedule_fixed_days', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Minggu .. 6=Sabtu (Carbon::dayOfWeek)
            $table->boolean('is_off_day')->default(false);
            $table->time('start_time')->nullable();
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('overtime_before_min')->default(0);
            $table->unsignedSmallInteger('overtime_before_break')->default(0);
            $table->unsignedSmallInteger('overtime_before_max')->default(0);
            $table->unsignedSmallInteger('overtime_after_min')->default(0);
            $table->unsignedSmallInteger('overtime_after_break')->default(0);
            $table->unsignedSmallInteger('overtime_after_max')->default(0);
            // Pengaturan Lanjutan (§2.5)
            $table->unsignedSmallInteger('checkin_buffer_minutes')->default(0);
            $table->unsignedSmallInteger('checkout_buffer_minutes')->default(0);
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_tolerance_minutes')->default(0);
            $table->unsignedTinyInteger('counted_work_days')->default(1);
            $table->unsignedSmallInteger('max_work_minutes')->nullable();
            $table->unsignedSmallInteger('half_day_threshold_minutes')->default(0);
            $table->unsignedSmallInteger('absent_threshold_minutes')->default(0);
            $table->timestamps();
            $table->unique(['schedule_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_fixed_days');
    }
};
