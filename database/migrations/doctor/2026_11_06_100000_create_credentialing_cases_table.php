<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB `doctor` - Fase 5: credentialing. 1 case = 1 proses (permohonan -> ... -> SK SPK) dengan 1 PDF final
 * (doctor_document_id -> doctor_documents, terenkripsi). Satu dokter boleh punya banyak case (riwayat
 * tidak ditimpa). SK SPK dilacak nomor & masa berlakunya (spk_*). rumah_sakit_id = m_companies.id,
 * created_by = users.id (ULID, lintas DB tanpa FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('doctor')->create('credentialing_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->char('rumah_sakit_id', 26)->index();
            $table->string('nomor', 150)->nullable();
            $table->string('jenis', 50)->nullable(); // kredensial awal / rekredensial
            $table->date('tanggal_pengajuan')->nullable();
            $table->enum('status', ['draft', 'pengajuan', 'verifikasi', 'kredensial', 'rekomendasi', 'sk_spk', 'selesai', 'ditolak'])
                ->default('draft')->index();
            $table->foreignId('doctor_document_id')->nullable()->constrained('doctor_documents')->nullOnDelete(); // PDF final
            $table->date('tanggal_selesai')->nullable();
            $table->string('spk_nomor', 150)->nullable();
            $table->date('spk_terbit')->nullable();
            $table->date('spk_berakhir')->nullable()->index();
            $table->text('notes')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('doctor')->dropIfExists('credentialing_cases');
    }
};
