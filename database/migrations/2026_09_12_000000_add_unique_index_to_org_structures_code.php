<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `m_org_structures.code` dipakai sistem lain (Keycloak dkk) sebagai kunci integrasi stabil
 * (bukan nama, yang bisa berubah) — lihat DepartmentsResource/DivisionsResource/
 * SubDivisionsResource & halaman publik /publik/unit-organisasi. Kolomnya sudah ada
 * (nullable, diisi lewat 3 resource itu), baris ini cuma nambah unique index biar tidak ada
 * 2 unit kebetulan pakai kode sama (NULL tetap boleh banyak, standar unique index).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_org_structures', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('m_org_structures', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });
    }
};
