<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penilaian honorarium per pertemuan (pengganti peran trainer_assessments, tapi nunjuk ke
     * lms_honorarium_variables/categories baru, bukan trainer_score_*). 1 pertemuan = 1 penilaian
     * (unique meeting_id) — trainer_id disalin dari meeting saat disimpan, total_score & honor_amount
     * dihitung ulang dari details() tiap kali detail berubah (lihat LmsMeetingHonorariumAssessment::recalculate()).
     */
    public function up(): void
    {
        Schema::create('lms_meeting_honorarium_assessments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->unique()->constrained('lms_meetings')->cascadeOnDelete();
            $table->foreignUlid('trainer_id')->nullable()->constrained('trainers')->nullOnDelete();
            $table->integer('total_score')->default(0);
            $table->double('honor_amount')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_meeting_honorarium_assessments');
    }
};
