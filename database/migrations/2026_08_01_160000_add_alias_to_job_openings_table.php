<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nama lain/singkat buat Lowongan Kerja - dipakai sebagai nama tampilan candidate-facing supaya
 * nama jabatan panjang macam "Perawat Lantai 2A Gedung B" bisa tampil ringkas "Perawat" tanpa
 * ganti nama jabatan aslinya di master. Kosong = fallback ke nama jabatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_openings', function (Blueprint $table) {
            $table->string('alias')->nullable()->after('job_position_id');
        });
    }

    public function down(): void
    {
        Schema::table('job_openings', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }
};
