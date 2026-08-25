<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil tes/wawancara ditaruh di baris pengajuan sendiri (pola sama legacy
 * pengajuan_karyawan_tetap.nilai_tes/nilai_psikotes/catatan_tes/catatan_wawancara) -
 * satu kejadian tes & satu kejadian wawancara per pengajuan, bukan riwayat berulang,
 * beda dari sikartap_test_schedules/sikartap_interview_schedules yang cuma nyimpan jadwalnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sikartap', function (Blueprint $table) {
            $table->decimal('nilai_tes', 5, 2)->nullable()->after('status_tes');
            $table->decimal('nilai_psikotes', 5, 2)->nullable()->after('nilai_tes');
            $table->text('catatan_tes')->nullable()->after('nilai_psikotes');
            $table->text('catatan_wawancara')->nullable()->after('status_wawancara');
        });
    }

    public function down(): void
    {
        Schema::table('sikartap', function (Blueprint $table) {
            $table->dropColumn(['nilai_tes', 'nilai_psikotes', 'catatan_tes', 'catatan_wawancara']);
        });
    }
};
