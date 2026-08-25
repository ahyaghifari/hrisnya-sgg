<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dokumen pendukung permohonan — mirror tabel `lampiran` sumber (another-app/sarpras). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_request_attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sarpras_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('nama_asli');
            $table->string('nama_simpan');
            $table->string('mime')->nullable();
            $table->unsignedInteger('ukuran')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_request_attachments');
    }
};
