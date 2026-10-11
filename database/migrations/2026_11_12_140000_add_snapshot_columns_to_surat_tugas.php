<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot identitas yang tercetak di surat, supaya (1) surat tidak ikut berubah kalau profil
 * karyawan diganti sesudah terbit dan (2) semua yang tampil di surat masuk hash tanda tangan.
 *  - surat_tugas_peserta: nama/nip/jabatan peserta saat surat diajukan;
 *  - surat_tugas.nip_ttd: NIP penanda tangan;
 *  - surat_tugas.payload_version: 1 = surat lama (hash tanpa snapshot, tetap valid), 2 = hash
 *    memuat company_id, akun penanda tangan, dan snapshot di atas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_tugas_peserta', function (Blueprint $table) {
            $table->string('nama')->nullable()->after('user_id');
            $table->string('nip')->nullable()->after('nama');
            $table->string('jabatan')->nullable()->after('nip');
        });

        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->string('nip_ttd')->nullable()->after('jabatan_ttd');
            $table->unsignedTinyInteger('payload_version')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('surat_tugas', fn (Blueprint $table) => $table->dropColumn(['nip_ttd', 'payload_version']));
        Schema::table('surat_tugas_peserta', fn (Blueprint $table) => $table->dropColumn(['nama', 'nip', 'jabatan']));
    }
};
