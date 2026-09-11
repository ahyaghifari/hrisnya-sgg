<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Regu"/tim shift — pengelompokan karyawan di 1 jadwal shift, dikelola Kepala Ruangan
     * (Update:ShiftScheduleAssignments) atau pengelola jadwal (Update:Schedule). Keanggotaan
     * riwayat lewat schedule_shift_team_members (pola sama ScheduleAssignment: effective_from/
     * effective_until), bukan kolom di tabel ini.
     */
    public function up(): void
    {
        Schema::create('schedule_shift_teams', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_shift_teams');
    }
};
