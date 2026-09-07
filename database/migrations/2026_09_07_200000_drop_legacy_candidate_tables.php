<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Buang 12 tabel sisa sistem pelamar lama (App\Models\Candidate\*, portal Livewire + panel
 * Filament `candidate`, dua-duanya sudah dihapus 2026-09-04). Modul rekrutmen yang aktif
 * sekarang jalan di koneksi DB terpisah (`recruitment`), tidak menyentuh tabel-tabel ini.
 * Model/service/repo/auth-guard `candidate` ikut dicabut di commit yang sama.
 *
 * `job_openings` TIDAK ikut di-drop — masih dipakai App\Services\Rekrutmen\RecruitmentSync.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Anak dulu (semua cascadeOnDelete ke candidates / job_applications), induk terakhir.
        Schema::dropIfExists('candt_self_assessments');
        Schema::dropIfExists('candt_additional_infos');
        Schema::dropIfExists('candt_references');
        Schema::dropIfExists('candt_work_experiences');
        Schema::dropIfExists('candt_organizations');
        Schema::dropIfExists('candt_trainings');
        Schema::dropIfExists('candt_educations');
        Schema::dropIfExists('candt_family_members');
        Schema::dropIfExists('recruitment_processes');
        Schema::dropIfExists('candidate_documents');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('candidates');
    }

    // ponytail: irreversible. Skema 12 tabel ada di migration create-nya masing-masing
    // (2025_12_* & 2026_04_09_*) kalau benar-benar perlu dibangun ulang.
    public function down(): void {}
};
