<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * sp_reasons dipakai dua master terpisah: `pelanggaran` (poin SP) dan `dasar` (Dasar Klarifikasi
 * Berita Acara). Baris lama = pelanggaran. Dasar tidak punya data bawaan: tiap perusahaan mengisi
 * sendiri lewat Master.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_reasons', function (Blueprint $table) {
            $table->string('kind')->default('pelanggaran')->after('company_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sp_reasons', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};
