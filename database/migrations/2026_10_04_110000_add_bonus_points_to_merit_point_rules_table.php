<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Poin bonus (instruksi user 2026-09-27): saldo Merit Point sekarang boleh lewat 100 ("bonus").
// Untuk rule bertipe Reward, kolom ini opsional - kalau diisi dan saldo karyawan SUDAH >= 100,
// rule itu memakai angka ini (biasanya lebih kecil) alih-alih `points` biasa. Null = rule tetap
// pakai `points` apa adanya walau saldo sudah >= 100 (perilaku lama, tidak berubah kalau HC
// tidak sempat mengisi kolom baru ini). Lihat MeritPointService::effectivePoints().
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merit_point_rules', function (Blueprint $table) {
            $table->integer('bonus_points')->nullable()->after('points');
        });
    }

    public function down(): void
    {
        Schema::table('merit_point_rules', function (Blueprint $table) {
            $table->dropColumn('bonus_points');
        });
    }
};
