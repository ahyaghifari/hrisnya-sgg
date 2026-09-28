<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siapa menandatangani 1 `documents` (bisa lebih dari 1 baris per dokumen, dibedakan
 * `signer_role`) + hash snapshot data saat itu (§3 docs/2026-09-28-tte-qr-internal-fase-1.md).
 * `signer_name`/`signer_position` SENGAJA disalin (snapshot), bukan dirujuk ke `users`, supaya
 * bukti tanda tangan tidak ikut berubah kalau data pegawai diedit belakangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('signer_role');
            $table->foreignUlid('signer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_name');
            $table->string('signer_position')->nullable();
            $table->char('document_hash', 64);
            $table->timestamp('signed_at');
            $table->timestamps();

            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signatures');
    }
};
