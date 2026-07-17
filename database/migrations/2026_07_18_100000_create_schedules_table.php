<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Header/master jadwal — ganti `m_shifts` (restrukturisasi total, lihat
     * planning/2026-07-18-restrukturisasi-jadwal-schedules.md). Tahap 1 (sesi ini): tabel ini
     * murni ADDITIVE, `m_shifts` & seluruh dependennya TIDAK disentuh/didrop — Filament yang
     * masih baca `m_shifts` sengaja dibiarkan jalan seperti biasa sampai Tahap 2 (adaptasi UI).
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('schedule_type'); // ScheduleType::FIXED|FLEXIBLE|SHIFT
            $table->foreignUlid('org_structure_id')->nullable()->constrained('m_org_structures')->nullOnDelete();
            $table->string('color')->nullable();
            // Jendela absensi khusus FLEXIBLE (§2.3 planning) — 1 nilai per jadwal, bukan per hari.
            $table->time('flexible_checkin_from')->nullable();
            $table->time('flexible_checkin_until')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
