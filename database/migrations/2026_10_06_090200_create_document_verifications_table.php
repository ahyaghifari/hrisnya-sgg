<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pintu masuk publik `/verify/{code}` (§5 docs/2026-09-28-tte-qr-internal-fase-1.md) - 1:1 dengan
 * `documents`, kode dibangkitkan SEKALI dan tetap selamanya (QR yang sudah tercetak di kertas
 * lama tidak boleh kedaluwarsa sendiri). TIDAK ADA kolom status/expires_at - "dicabut"/"berlaku"
 * dihitung LIVE dari model dokumen asli (Signable::isRevoked()) tiap halaman verifikasi dibuka,
 * bukan disalin ke sini (jangan sampai state mencong dari sumber).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_verifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->unique()->constrained('documents')->cascadeOnDelete();
            $table->string('verification_code')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_verifications');
    }
};
