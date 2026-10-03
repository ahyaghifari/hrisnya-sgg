<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Samakan `surat` dengan struktur sistem lama (audit_syifag.surat) supaya data lama bisa dimigrasi 1:1:
 * tipe_surat (masuk/keluar), status (Tersimpan/Draft), judul_surat, perihal opsional, deskripsi.
 * unit_id & nomor_urut jadi opsional — surat masuk punya nomor eksternal & tidak lewat generator.
 * id_departemen sistem lama BELUM dibawa (menunggu keputusan master departemen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('persuratan')->table('surat', function (Blueprint $table) {
            $table->enum('tipe_surat', ['masuk', 'keluar'])->default('keluar')->after('nomor');
            $table->enum('status', ['Tersimpan', 'Draft'])->default('Tersimpan')->after('tipe_surat');
            $table->string('judul_surat')->nullable()->after('status');
            $table->text('deskripsi')->nullable()->after('perihal');
            $table->string('perihal')->nullable()->change();
            $table->unsignedInteger('nomor_urut')->nullable()->change();
            $table->unsignedBigInteger('unit_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('persuratan')->table('surat', function (Blueprint $table) {
            $table->dropColumn(['tipe_surat', 'status', 'judul_surat', 'deskripsi']);
        });
    }
};
