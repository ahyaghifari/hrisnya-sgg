<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cluster honorarium lama (trainer_score_* / trainer_assessments / details) — digantikan
     * konsepnya oleh lms_honorarium_variables/categories/lms_honorarium +
     * lms_meeting_honorarium_assessments/details (per pertemuan, bukan per course). Tab admin
     * "Penilaian Trainer" (LmsCoursesForm) dan pemanggilnya di CreateLmsCourses/EditLmsCourses
     * sudah dihapus bareng migration ini. trainer_assessment_results TIDAK ikut di-drop — masih
     * dipakai halaman admin "Gaji Trainer" & tab portal "Penilaian dan Gaji Pemateri" yang baru.
     *
     * Urutan drop mengikuti FK: trainer_assessment_details (FK ke 3 tabel lain) lebih dulu,
     * baru trainer_assessments, trainer_score_categories (FK ke variables), trainer_score_variables,
     * trainer_score_ranges (independen).
     */
    public function up(): void
    {
        Schema::dropIfExists('trainer_assessment_details');
        Schema::dropIfExists('trainer_assessments');
        Schema::dropIfExists('trainer_score_categories');
        Schema::dropIfExists('trainer_score_variables');
        Schema::dropIfExists('trainer_score_ranges');
    }

    /**
     * Sengaja tidak direkonstruksi — ini pembersihan data lama yang disengaja, bukan perubahan
     * skema yang perlu dibalik. Kalau butuh rollback, restore dari backup database sebelum migrasi ini.
     */
    public function down(): void
    {
        //
    }
};
