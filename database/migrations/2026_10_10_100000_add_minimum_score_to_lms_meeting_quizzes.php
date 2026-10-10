<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_meeting_quizzes', function (Blueprint $table) {
            $table->unsignedSmallInteger('minimum_score')->default(0)->after('is_graduation_requirement');
        });

        // Skor minimum pindah dari pelatihan ke kuis penentu kelulusan: salin nilai lama.
        DB::table('lms_meeting_quizzes')
            ->where('is_graduation_requirement', true)
            ->update([
                'minimum_score' => DB::raw('(select minimum_score from lms_courses where lms_courses.id = (select course_id from lms_meetings where lms_meetings.id = lms_meeting_quizzes.meeting_id))'),
            ]);
    }

    public function down(): void
    {
        Schema::table('lms_meeting_quizzes', function (Blueprint $table) {
            $table->dropColumn('minimum_score');
        });
    }
};
