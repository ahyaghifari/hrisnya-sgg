<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berita Acara berdiri sendiri (instruksi user 2026-10-01): HC bikin BA dulu, SP baru dibuat
 * setelah BA selesai & diputuskan layak SP. Karena itu `sp_id` jadi nullable (diisi saat SP
 * dibuat dari BA ini) dan BA punya karyawan/company/penanda tangan/status sendiri.
 *
 * `status` NULL = BA lama (bagian dari SP, alur lama) - sengaja tidak di-backfill supaya BA yang
 * sudah ditandatangani tidak berubah perilaku/hash-nya.
 *
 * `subject_clarification` TETAP dipakai, sekarang artinya klarifikasi karyawan APA ADANYA;
 * `klarifikasi_parafrase` = versi HC. `riwayat_klarifikasi` menyimpan versi sebelumnya kalau
 * karyawan mengajukan ulang klarifikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_reports', function (Blueprint $table) {
            $table->dropForeign(['sp_id']);
        });

        Schema::table('sp_reports', function (Blueprint $table) {
            $table->ulid('sp_id')->nullable()->change();
        });

        Schema::table('sp_reports', function (Blueprint $table) {
            $table->foreign('sp_id')->references('id')->on('employee_sps')->cascadeOnDelete();

            $table->foreignUlid('user_id')->nullable()->after('sp_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('company_id')->nullable()->after('user_id')->constrained('m_companies')->restrictOnDelete();
            $table->foreignUlid('created_by')->nullable()->after('company_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('knows_by')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->nullable()->after('knows_by');
            $table->string('keputusan')->nullable()->after('status');
            $table->text('klarifikasi_parafrase')->nullable()->after('subject_clarification');
            $table->timestamp('klarifikasi_diajukan_at')->nullable()->after('klarifikasi_parafrase');
            $table->json('riwayat_klarifikasi')->nullable()->after('klarifikasi_diajukan_at');
        });
    }

    public function down(): void
    {
        Schema::table('sp_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('knows_by');
            $table->dropColumn(['status', 'keputusan', 'klarifikasi_parafrase', 'klarifikasi_diajukan_at', 'riwayat_klarifikasi']);
        });
    }
};
