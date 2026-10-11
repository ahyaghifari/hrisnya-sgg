<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat Perintah Tugas (atasan/direktur menugaskan bawahan bekerja di luar kantor). Header
 * surat = dokumen yang ditandatangani (Signable); peserta & periode dipisah karena satu surat
 * bisa memuat banyak orang dan banyak rentang tanggal terputus (2-3 Agustus, 5-6 Agustus).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Master calon penanda tangan per company: 1 default, bisa diganti saat membuat surat
        // (mis. default sedang cuti). Nama/jabatan boleh dikustom (mis. "Plt. Direktur").
        Schema::create('penandatangan_surat_tugas', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nama');
            $table->string('jabatan');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('surat_tugas', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('nomor')->nullable();
            $table->foreignUlid('penandatangan_id')->constrained('penandatangan_surat_tugas');
            // Snapshot: surat lama tidak berubah kalau master penanda tangan diganti.
            $table->string('nama_ttd');
            $table->string('jabatan_ttd');
            $table->text('tugas');
            $table->string('tempat');
            $table->string('transportasi')->nullable();
            $table->string('waktu')->nullable(); // free text: "08.00-16.00" / "Sampai selesai"
            $table->string('status')->default('menunggu_ttd');
            $table->foreignUlid('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('surat_tugas_peserta', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('surat_tugas_id')->constrained('surat_tugas')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users');
            $table->unique(['surat_tugas_id', 'user_id']);
        });

        Schema::create('surat_tugas_periode', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('surat_tugas_id')->constrained('surat_tugas')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_tugas_periode');
        Schema::dropIfExists('surat_tugas_peserta');
        Schema::dropIfExists('surat_tugas');
        Schema::dropIfExists('penandatangan_surat_tugas');
    }
};
