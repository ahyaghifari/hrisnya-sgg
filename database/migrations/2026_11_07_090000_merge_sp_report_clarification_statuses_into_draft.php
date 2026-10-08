<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Berita Acara: tahap klarifikasi digabung ke draft (instruksi user 2026-10-07) - semua isian HC
 * diisi sekaligus. BA yang masih di tahap klarifikasi dikembalikan ke draft; isinya tetap.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sp_reports')->whereIn('status', ['menunggu_klarifikasi', 'klarifikasi_diajukan'])->update(['status' => 'draft']);
    }

    public function down(): void
    {
        // Tidak bisa dipulihkan: tahap asal tiap BA tidak tersimpan.
    }
};
