<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB `doctor` - Fase 3: lisensi dokter (SIP, STR, STR-KT) dengan nomor & masa berlaku. File scan-nya
 * disimpan sebagai doctor_documents (doctor_document_id, opsional). Status 'akan_expired'/'expired'
 * dihitung saat tampil (DoctorLicense::status_efektif); job reminder (Fase 4) yang menulisnya ke kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('doctor')->create('doctor_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->enum('jenis', ['SIP', 'STR', 'STR_KT']);
            $table->string('nomor', 150)->nullable();
            $table->date('tanggal_terbit')->nullable();
            $table->date('tanggal_berlaku')->nullable();
            $table->date('tanggal_berakhir')->nullable()->index();
            $table->foreignId('doctor_document_id')->nullable()->constrained('doctor_documents')->nullOnDelete();
            $table->enum('status', ['aktif', 'akan_expired', 'expired', 'nonaktif'])->default('aktif');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('jenis');
        });
    }

    public function down(): void
    {
        Schema::connection('doctor')->dropIfExists('doctor_licenses');
    }
};
