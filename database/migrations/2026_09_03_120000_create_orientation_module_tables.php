<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Orientasi (self-service, pola Sikartap). Menggantikan `user_orientations` versi awal.
 * Lihat planning/2026-09-03-modul-orientasi-dan-nav-status-saya.md.
 *
 * SATU tabel saja (keputusan user): lampiran & skor disimpan sebagai JSON di baris ini.
 * Indikator penilaian TIDAK di sini — disimpan di `settings` (key `orientation_criteria`,
 * JSON per company) supaya HR bisa ubah tanpa deploy dan tanpa tabel tambahan.
 *
 * - status   : draft -> menunggu_persetujuan -> dinilai -> selesai / dibatalkan
 *   Karyawan boleh mengajukan BERKALI-KALI (>1 baris per karyawan sepanjang waktu); syaratnya
 *   cuma tidak ada pengajuan yang masih berjalan.
 * - decision : lulus / perpanjang / tidak_lulus (diisi penilai di step penilaian)
 * - attachments : [{ "path": "...", "name": "..." }, ...] bukti kegiatan orientasi
 * - scores      : [{ "criterion": "...", "score": 4, "comment": "..." }, ...] diisi saat dinilai
 * - approval    : Approval Engine, module_type = ORIENTATION_REQUEST, module_id = id baris ini
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_orientations');

        Schema::create('orientation_submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->string('decision')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->date('extended_until')->nullable();
            $table->text('note')->nullable();
            $table->json('attachments')->nullable();
            $table->json('scores')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orientation_submissions');
    }
};
