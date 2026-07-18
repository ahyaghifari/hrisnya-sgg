<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roster anggota koperasi + cicilan bulanan tetapnya — sumber yang otomatis di-sync ke
     * `payroll_manual_lines` (component=koperasi) tiap Generate/Regenerate Payroll (lihat
     * PayrollService::syncKoperasiManualLines()), jadi finance tidak perlu input manual tiap
     * periode. `is_active` dipakai buat berhenti motong begitu cicilan lunas, tanpa hapus baris
     * (riwayat amount tetap ada).
     */
    public function up(): void
    {
        Schema::create('user_koperasi', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_koperasi');
    }
};
