<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Level (urutan pangkat, makin tinggi angka = makin tinggi jenjang) - dipakai buat sortir
 * Pangkat dari tertinggi ke terendah, pola sama dengan Kelas::level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->integer('level')->nullable()->after('golongan');
        });
    }

    public function down(): void
    {
        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
