<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bukti presensi (scan) LEMBUR di hari yang jadwalnya libur - terpisah dari attendance_records
 * supaya scan itu tidak dihitung masuk/terlambat (pola sama dengan extra_shift_attendance_records).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_attendance_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->decimal('working_minutes', 8, 2)->nullable();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_attendance_records');
    }
};
