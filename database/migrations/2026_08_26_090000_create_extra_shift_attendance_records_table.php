<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pencatatan jam kerja aktual extra shift - terpisah dari attendance_records (yang unique per
 * nip+date) karena 1 hari bisa punya shift normal + extra shift sekaligus. Versi pertama:
 * input manual saja (lihat catatan "Integrasi ke absensi" di planning/2026-07-31-fitur-extra-
 * shift.md §5), bukan dari access_logs - jadi tidak ada kolom source/is_auto_calculated,
 * dan sengaja tidak ada work_day_weight (extra shift tidak dihitung sebagai hari kerja
 * tambahan buat kuota bulanan, cuma jam kerjanya saja yang dicatat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_shift_attendance_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('extra_shift_assignment_id')->unique()->constrained('extra_shift_assignments')->cascadeOnDelete();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->decimal('working_minutes', 8, 2)->nullable();
            $table->float('late_minutes')->nullable();
            $table->float('early_leave_minutes')->nullable();
            $table->float('overtime_minutes')->nullable();
            $table->string('status')->nullable();
            $table->boolean('out_of_window')->default(false);
            $table->foreignUlid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_shift_attendance_records');
    }
};
