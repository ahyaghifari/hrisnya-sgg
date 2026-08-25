<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kepala dokumen permohonan Sarana & Prasarana (rewrite dari another-app/sarpras, bukan port).
 * Tidak reuse attendance_requests (domain beda total) dan tidak simpan kabid_nama/direktur_nama
 * snapshot di sini seperti sumber — identitas penandatangan snapshot-nya hidup di
 * sarpras_request_signatures, redundan kalau diduplikasi di sini juga. Tidak ada company_id
 * eksplisit, scope company didapat lewat relasi user->company_id (pola sama sikartap).
 * Kolom `unit` sengaja masih string bebas — mapping ke OrgStructure/JobPosition RS belum
 * diputuskan (lihat planning/2026-08-25-migrasi-sarpras-ke-hris.md §3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('kode')->unique();
            $table->string('unit');
            $table->string('jenis')->default('perbaikan');
            $table->string('prioritas')->default('normal');
            $table->string('perihal');
            $table->text('uraian');
            $table->string('status')->default('diajukan');

            $table->text('catatan_kabid')->nullable();
            $table->foreignUlid('kabid_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('kabid_at')->nullable();

            $table->text('catatan_direktur')->nullable();
            $table->foreignUlid('direktur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('direktur_at')->nullable();

            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_requests');
    }
};
