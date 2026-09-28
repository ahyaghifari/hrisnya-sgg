<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registry TIPIS untuk TTE + QR internal (Fase 1, docs/2026-09-28-tte-qr-internal-fase-1.md) -
 * 1 baris per dokumen terbit (SP, Berita Acara, nanti Surat Tugas). TIDAK menyimpan isi surat
 * (poin pelanggaran, klarifikasi, dst tetap di tabel sumber masing-masing) - cuma metadata buat
 * halaman "Semua Dokumen Terbit" lintas jenis tanpa UNION manual. `document_signatures` dan
 * `document_verifications` nempel ke sini lewat FK biasa, BUKAN polymorphic lagi (polymorphic-nya
 * cukup sekali di sini, lihat documentable_type/id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('documentable_type');
            $table->ulid('documentable_id');
            $table->string('document_type');
            $table->string('document_number');
            $table->string('title');
            $table->timestamp('issued_at');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['documentable_type', 'documentable_id']);
            $table->index(['document_type', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
