<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache tampilan widget Karir (planning/2026-09-23-sp-dan-merit-point.md §3.5) — BUKAN sumber
 * kebenaran. active_sp_level bisa basi murni karena tanggal lewat tanpa event apa pun, harus
 * direfresh job harian + eager saat employee_sps baru dibuat. Validasi bisnis WAJIB live-query
 * ke employee_sps/merit_point_transactions, tidak boleh baca tabel ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_hr_balances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('merit_point')->default(0);
            $table->string('active_sp_level')->nullable();
            $table->foreignUlid('active_sp_id')->nullable()->constrained('employee_sps')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_hr_balances');
    }
};
