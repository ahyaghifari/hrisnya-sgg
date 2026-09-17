<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jabatan yang ditandai "dinilai atasan langsung" untuk milestone perjalanan status karyawan
 * (context sama seperti `sikartap_reviewers`). Kalau jabatan karyawan target ADA di sini →
 * SikartapReviewerResolver skip pencarian Departemen, langsung resolve ke pemegang aktif
 * JobPositions.parent_id (naik terus kalau kosong). Jabatan yang TIDAK ditandai di sini tetap
 * pakai skema lama (by Departemen, `sikartap_reviewers`).
 *
 * Kasus pemicu (2026-09-17, instruksi user): RS — perawat dinilai atasan langsung (kepala
 * ruangan masing-masing), bukan 1 jabatan tetap se-departemen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikartap_direct_supervisor_positions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('context');
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->foreignUlid('job_position_id')->constrained('m_job_positions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['context', 'company_id', 'job_position_id'], 'sikartap_direct_supervisor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikartap_direct_supervisor_positions');
    }
};
