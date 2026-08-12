<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Honor di TrainerAssessmentResults (kunci course_id+trainer_id) dihitung per PERTEMUAN
 * (LmsCourseDetailPage tab "Penilaian dan Gaji Pemateri" — course multi-pertemuan pilih
 * pertemuan mana dulu, beda pertemuan bisa beda hasil), tapi pertemuan mana yang menghasilkan
 * honor_amount saat ini tidak pernah disimpan. Kolom ini dipakai kedua jalur simpan
 * (LmsMeetingHonorariumAssessment::syncToTrainerAssessmentResult() &
 * MeetingHonorariumService::saveFixedAmount()) supaya portal Gaji Pemateri bisa tampilkan
 * pertemuannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_assessment_results', function (Blueprint $table) {
            $table->foreignUlid('meeting_id')->nullable()->after('trainer_id')
                ->constrained('lms_meetings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trainer_assessment_results', function (Blueprint $table) {
            $table->dropForeign(['meeting_id']);
            $table->dropColumn('meeting_id');
        });
    }
};
