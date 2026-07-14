<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaikan typo nama tabel (gap #10, knowledge/markdown/analisis-lms.md §11) — "assesments"
 * jadi "assessment", sekalian disamakan singular seperti tabel sibling-nya
 * (trainer_assessments, trainer_assessment_details). 5 baris data, tidak ada tabel lain yang
 * FK ke tabel ini — aman untuk rename langsung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('trainer_assesments_results', 'trainer_assessment_results');
    }

    public function down(): void
    {
        Schema::rename('trainer_assessment_results', 'trainer_assesments_results');
    }
};
