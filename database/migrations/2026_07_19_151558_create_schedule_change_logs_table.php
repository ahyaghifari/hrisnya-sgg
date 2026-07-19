<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot kondisi jadwal SEBELUM tiap kali diedit (CreateSchedulePage::save(), jalur
     * edit) — niru "Riwayat" fingerspot.io (knowledge/absensi): klik baris riwayat nampilin
     * kondisi lama lewat popup. `snapshot` (JSON) isinya nama/tipe/jendela absensi + array hari
     * (sama bentuk `$days` di CreateSchedulePage) pada saat SEBELUM perubahan itu disimpan.
     */
    public function up(): void
    {
        Schema::create('schedule_change_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignUlid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_change_logs');
    }
};
