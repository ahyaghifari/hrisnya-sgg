<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berita Acara (instruksi user 2026-09-25) - 1:1 dengan employee_sps, catatan investigasi HC-GA
 * sebelum SP terbit: kejadian, klarifikasi karyawan (SUBJECT_CLARIFICATION, menggantikan
 * employee_response lama), analisis, dan rencana tindak lanjut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sp_id')->unique()->constrained('employee_sps')->cascadeOnDelete();
            $table->text('kejadian')->nullable();
            $table->text('subject_clarification')->nullable();
            $table->text('analysis')->nullable();
            $table->text('followup_plan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_reports');
    }
};
