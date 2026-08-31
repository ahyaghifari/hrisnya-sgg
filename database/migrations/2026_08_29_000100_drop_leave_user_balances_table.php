<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus `leave_user_balances` — kuota cuti sekarang dihitung on-the-fly saat ditampilkan
 * (kuota dari `m_leave_types.quota_days`, terpakai dari Σ pengajuan `approved` di periode
 * berjalan; lihat LeaveReqRepository::getLeaveUserBalance). Kolom `used`/`quota` di tabel
 * ini sudah tidak dibaca, `period_start`/`period_end` diganti dengan tahun kalender berjalan.
 * Command `leave:reset-balance` + jadwal cron-nya ikut dihapus.
 *
 * `m_leave_types.reset_period` TETAP ADA — dipakai sebagai penanda window perhitungan
 * (yearly / monthly untuk cuti haid / none per-kejadian), bukan lagi buat trigger cron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('leave_user_balances');
    }

    public function down(): void
    {
        Schema::create('leave_user_balances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('leave_type_id')->constrained('m_leave_types')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('quota')->default(0);
            $table->integer('used')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'leave_type_id', 'period_start']);
        });
    }
};
