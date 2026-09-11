<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keanggotaan regu PER BULAN (kolom `period`, format 'Y-m') — bukan riwayat tanggal-efektif
     * bebas. Regu biasanya diganti per bulan (rotasi), jadi disederhanakan: 1 baris = 1 user di
     * 1 regu untuk 1 bulan tertentu di 1 jadwal. Ganti regu bulan berjalan/depan = upsert baris
     * bulan itu, bulan lain (lewat) tidak ikut berubah. Lihat ScheduleShiftTeamMember::setForPeriod().
     */
    public function up(): void
    {
        Schema::create('schedule_shift_team_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained('schedule_shift_teams')->cascadeOnDelete();
            $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('period', 7); // 'Y-m', mis. '2026-09'
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['schedule_id', 'user_id', 'period'], 'sstm_schedule_user_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_shift_team_members');
    }
};
