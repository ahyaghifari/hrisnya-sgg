<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak kode verifikasi tanda tangan yang dibatalkan karena revisi dokumen (Document::revise), supaya
 * QR lama menampilkan "dibatalkan karena revisi" alih-alih "tidak ditemukan".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_voided_verifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('verification_code')->unique();
            $table->string('signer_role');
            $table->string('signer_name');
            $table->string('signer_position')->nullable();
            $table->dateTime('signed_at');
            $table->text('reason');
            $table->dateTime('voided_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_voided_verifications');
    }
};