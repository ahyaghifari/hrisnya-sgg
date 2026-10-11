<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda tangan Surat Perintah Tugas jadi 1 orang per company (diatur di Pengaturan
 * Kepegawaian, bukan dipilih per surat) dan di-snapshot per surat (ttd_user_id + nama_ttd +
 * jabatan_ttd). Tanggal selesai periode boleh kosong = sampai selesai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->foreignUlid('ttd_user_id')->nullable()->after('nomor')->constrained('users');
        });

        // Surat yang sudah ada: ambil user penanda tangan dari master sebelum kolom lamanya dibuang.
        DB::table('surat_tugas')->orderBy('id')->each(function ($surat) {
            DB::table('surat_tugas')->where('id', $surat->id)->update([
                'ttd_user_id' => DB::table('penandatangan_surat_tugas')->where('id', $surat->penandatangan_id)->value('user_id'),
            ]);
        });

        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('penandatangan_id');
        });

        Schema::table('penandatangan_surat_tugas', function (Blueprint $table) {
            $table->dropColumn('is_default');
            $table->unique('company_id');
        });

        Schema::table('surat_tugas_periode', function (Blueprint $table) {
            $table->date('end_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('surat_tugas_periode', function (Blueprint $table) {
            $table->date('end_date')->nullable(false)->change();
        });

        Schema::table('penandatangan_surat_tugas', function (Blueprint $table) {
            $table->dropUnique(['company_id']);
            $table->boolean('is_default')->default(false);
        });

        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->foreignUlid('penandatangan_id')->nullable()->constrained('penandatangan_surat_tugas');
            $table->dropConstrainedForeignId('ttd_user_id');
        });
    }
};
