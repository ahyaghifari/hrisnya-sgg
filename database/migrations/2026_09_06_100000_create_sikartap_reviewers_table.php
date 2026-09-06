<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penunjukan penilai/reviewer buat milestone "perjalanan status karyawan" — general, dipakai
 * lintas konteks (`context`): `orientation` (penilaian orientasi), `sikartap_idea` (review ide
 * inovasi Sikartap; migrasi dari sikartap_ide_inovasi_reviewers nyusul), nanti `contract`.
 *
 * Reviewer = JABATAN (`job_position_id`), bukan user langsung — tahan personil ganti. Resolver
 * (App\Services\SikartapReviewerResolver): naik dari jabatan karyawan ke Departemen level-1,
 * cari baris `(context, company_id, org_structure_id)`, resolve jabatan ke pemegang aktif;
 * kosong → naik ke departemen parent; mentok kosong → pemanggil tolak + minta HR isi.
 *
 * `sikartap_director` SENGAJA TIDAK di sini — Direktur pemutus lewat Setting langsung per
 * company (kalau nanti dilepas dari Approval Engine).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikartap_reviewers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('context');
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->foreignUlid('org_structure_id')->constrained('m_org_structures')->cascadeOnDelete();
            $table->foreignUlid('job_position_id')->constrained('m_job_positions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['context', 'company_id', 'org_structure_id', 'job_position_id'], 'sikartap_reviewers_unique');
            $table->index(['context', 'company_id', 'org_structure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikartap_reviewers');
    }
};
