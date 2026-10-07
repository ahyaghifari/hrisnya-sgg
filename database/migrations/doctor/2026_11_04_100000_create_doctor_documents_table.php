<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB `doctor` - Fase 2: dokumen dokter. File terenkripsi di disk privat 'local' (file_path),
 * dibuka lewat route berizin - bukan URL publik. Soft delete: riwayat dokumen tidak hilang.
 * uploaded_by = users.id HRIS (ULID, tanpa FK lintas DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('doctor')->create('doctor_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->string('document_type', 50)->index();
            $table->string('file_path', 500);
            $table->string('file_name', 255); // nama asli upload, untuk tampilan/unduhan
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('document_number', 150)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expired_at')->nullable()->index();
            $table->enum('status', ['aktif', 'nonaktif', 'expired'])->default('aktif');
            $table->text('notes')->nullable();
            $table->char('uploaded_by', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('doctor')->dropIfExists('doctor_documents');
    }
};
