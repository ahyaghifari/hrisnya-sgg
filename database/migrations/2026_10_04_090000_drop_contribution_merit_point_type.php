<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Tipe Merit Point cuma 2 (Reward/Penalty, instruksi user 2026-09-27) - baris 'contribution'
// yang sudah ada (kalau ada, mis. dari MeritPointDemoSeeder lama) dipetakan ke 'reward', sama
// seperti pilihan MeritPointDemoSeeder yang diperbarui (poinnya selalu positif).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('merit_point_rules')->where('type', 'contribution')->update(['type' => 'reward']);
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - tidak ada cara membedakan baris 'reward' yang tadinya
        // 'contribution' dari yang memang dibuat 'reward' sejak awal.
    }
};
