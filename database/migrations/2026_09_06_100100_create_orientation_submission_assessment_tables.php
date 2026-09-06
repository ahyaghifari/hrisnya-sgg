<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skor penilaian orientasi — ganti kolom `orientation_submissions.scores` (JSON blob) jadi
 * 2 tabel parent/child yang normal (pola sama assessment_users -> assessment_scores):
 *
 * - orientation_submission_assessments : 1 baris = 1 indikator per pengajuan (snapshot dari
 *   Setting `orientation_criteria` saat dinilai; ubah setting nanti TIDAK ngubah penilaian lama).
 * - orientation_submission_scores      : 1 baris = skor 1 indikator pada 1 BULAN periode
 *   orientasi. 3 bulan x 5 indikator = 5 baris assessments + 15 baris scores.
 *
 * `orientation_submissions.scores` di-drop (0 baris data saat migrasi ini dibuat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orientation_submission_assessments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('orientation_submission_id');
            $table->string('label');
            $table->text('comment')->nullable();
            $table->integer('sort_order')->default(1);
            $table->timestamps();

            $table->foreign('orientation_submission_id', 'osa_submission_fk')
                ->references('id')->on('orientation_submissions')->cascadeOnDelete();
            $table->index(['orientation_submission_id', 'sort_order'], 'osa_submission_sort_idx');
        });

        Schema::create('orientation_submission_scores', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('orientation_submission_assessment_id');
            $table->date('period_month');
            $table->decimal('score', 4, 2)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('orientation_submission_assessment_id', 'oss_assessment_fk')
                ->references('id')->on('orientation_submission_assessments')->cascadeOnDelete();
            $table->unique(['orientation_submission_assessment_id', 'period_month'], 'oss_assessment_month_unique');
        });

        Schema::table('orientation_submissions', function (Blueprint $table) {
            $table->dropColumn('scores');
        });
    }

    public function down(): void
    {
        Schema::table('orientation_submissions', function (Blueprint $table) {
            $table->json('scores')->nullable();
        });
        Schema::dropIfExists('orientation_submission_scores');
        Schema::dropIfExists('orientation_submission_assessments');
    }
};
