<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Golongan digabung jadi atribut Pangkat (teks bebas, mis. "III/a") - bukan lagi tabel/FK
 * terpisah. Lihat migration pasangannya
 * (2026_07_29_110100_drop_golongan_id_and_golongans_table.php) yang menghapus golongan_id
 * dari m_job_positions/user_profiles dan drop m_golongans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->string('golongan')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('m_pangkats', function (Blueprint $table) {
            $table->dropColumn('golongan');
        });
    }
};
