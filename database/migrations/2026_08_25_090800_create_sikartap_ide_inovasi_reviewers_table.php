<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penunjukan manual reviewer atasan (tahap 1 Ide Inovasi) per node Divisi (level 2) org struktur
 * — Kabag/Kabid itu jabatan per divisi, bukan per department (level 1) yang menaungi banyak
 * divisi sekaligus, lihat Sikartap::ideInovasiOrgDivision().
 *
 * head_id di m_org_structures nyaris tidak pernah diisi di data riil (1 dari 84 baris saat
 * fitur ini dibangun, 2026-08-25) - jadi TIDAK bisa diandalkan sendirian buat nentuin siapa
 * atasan yang berhak menilai. Tabel ini penunjukan eksplisit dari admin (siapa saja, tidak
 * harus pemegang jabatan tertentu) sebagai fallback/pelengkap. Sikartap::ideInovasiAtasanCandidates()
 * gabung union dari head_id (kalau ada) + baris di tabel ini - siapa saja yang match salah satu
 * boleh review, atasan otomatis (head_id) tidak pernah "kalah" oleh lupa-ditunjuk-manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikartap_ide_inovasi_reviewers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->foreignUlid('org_structure_id')->constrained('m_org_structures')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['org_structure_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikartap_ide_inovasi_reviewers');
    }
};
