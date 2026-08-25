<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 — rewrite upload_ide_inovasi.php + verifikasi_ide_inovasi.php legacy (versi tiered,
 * direkonstruksi dari upload_ide_inovasi.php & cek_nilai_ide_inovasi.php karena file verifikasi
 * production-nya sudah tidak ada, lihat knowledge/markdown/analisis-sikartap-migrasi.md).
 * 3 tingkat: atasan (wajib, blocking), direktur & komisaris (opsional, tidak blocking, independen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikartap_ide_inovasis', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sikartap_id')->unique()->constrained('sikartap')->cascadeOnDelete();

            $table->string('file_ide_inovasi');
            $table->text('catatan_penolakan')->nullable();

            // Tahap 1 — atasan (Kepala Bagian/Bidang di root org struktur karyawan), wajib & blocking.
            $table->string('status_atasan')->default('pending');
            $table->text('catatan_atasan')->nullable();
            $table->foreignUlid('reviewed_by_atasan')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at_atasan')->nullable();

            $table->unsignedTinyInteger('skor_identifikasi_masalah')->nullable();
            $table->unsignedTinyInteger('skor_analisis_masalah')->nullable();
            $table->unsignedTinyInteger('skor_kualitas_inovasi')->nullable();
            $table->unsignedTinyInteger('skor_potensi_manfaat')->nullable();
            $table->unsignedTinyInteger('skor_presentasi')->nullable();
            $table->decimal('nilai_akhir_atasan', 5, 2)->nullable();
            $table->string('klasifikasi_atasan')->nullable();

            // Tahap 2 — direktur, opsional & tidak blocking.
            $table->string('status_direktur')->default('pending');
            $table->text('catatan_direktur')->nullable();
            $table->foreignUlid('reviewed_by_direktur')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at_direktur')->nullable();

            // Tahap 3 — komisaris, opsional & tidak blocking.
            $table->string('status_komisaris')->default('pending');
            $table->text('catatan_komisaris')->nullable();
            $table->foreignUlid('reviewed_by_komisaris')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at_komisaris')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikartap_ide_inovasis');
    }
};
