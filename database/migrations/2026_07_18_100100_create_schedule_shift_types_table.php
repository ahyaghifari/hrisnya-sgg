<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kode-kode shift (Pagi/Siang/Malam/dst) di bawah 1 `schedules` tipe SHIFT — ganti isi baris
     * `m_shifts` lama. 8 kolom "Pengaturan Lanjutan" (§2.5 planning) diambil apa adanya dari
     * KONFIGURASI JADWAL SHIFT.png.
     */
    public function up(): void
    {
        Schema::create('schedule_shift_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('shift_type')->nullable(); // ShiftType::PAGI|SIANG|MALAM, dipindah dari m_shifts.shift_type
            $table->time('start_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->time('end_time');
            $table->boolean('is_overnight')->default(false);
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
            $table->string('color')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['schedule_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_shift_types');
    }
};
