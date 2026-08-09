<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batas pelamar & batas tanggal lamaran - dipakai sistem Rekrutmen eksternal (port 8001,
 * database sgg_rekrutmen) buat membatasi jumlah/waktu pelamar boleh masuk. Nullable = tidak
 * dibatasi (default sekarang, sebelum admin isi manual saat buka lowongan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_openings', function (Blueprint $table) {
            $table->unsignedInteger('max_applicants')->nullable()->after('quota');
            $table->date('application_deadline')->nullable()->after('max_applicants');
        });
    }

    public function down(): void
    {
        Schema::table('job_openings', function (Blueprint $table) {
            $table->dropColumn(['max_applicants', 'application_deadline']);
        });
    }
};
