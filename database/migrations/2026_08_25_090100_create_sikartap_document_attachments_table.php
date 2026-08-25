<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1 baris = 1 jenis dokumen per pengajuan (bukan 1 baris per aksi verifikasi).
 * Rewrite dari verifikasi_berkas.php legacy: dulu 2 tabel terpisah
 * (dokumen_administratif kolom-per-jenis + status_dokumen baris-per-jenis) --
 * digabung di sini karena isinya 1:1 selalu dipakai bareng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikartap_document_attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sikartap_id')->constrained('sikartap')->cascadeOnDelete();
            $table->string('doc_type');
            $table->string('file_path');
            $table->string('status')->default('pending');
            $table->text('catatan_penolakan')->nullable();
            $table->timestamps();

            $table->unique(['sikartap_id', 'doc_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikartap_document_attachments');
    }
};
