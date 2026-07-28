<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_course_feedbacks', function (Blueprint $table) {
            $table->foreignUlid('respondent_id')->nullable()->after('course_id')
                ->constrained('lms_respondents')->cascadeOnDelete();
        });

        $skipped = [];

        foreach (DB::table('lms_course_feedbacks')->orderBy('id')->cursor() as $row) {
            $respondentId = DB::table('lms_respondents')
                ->where('course_id', $row->course_id)
                ->where('user_id', $row->user_id)
                ->value('id');

            if (! $respondentId) {
                $skipped[] = $row->id;
                continue;
            }

            DB::table('lms_course_feedbacks')->where('id', $row->id)->update([
                'respondent_id' => $respondentId,
            ]);
        }

        $unmatched = DB::table('lms_course_feedbacks')->whereNull('respondent_id')->count();

        if ($unmatched > 0) {
            throw new \RuntimeException(
                "lms_course_feedbacks has {$unmatched} row(s) without a matching respondent after backfill: " . implode(', ', $skipped)
            );
        }

        Schema::table('lms_course_feedbacks', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->foreignUlid('respondent_id')->nullable(false)->change();
        });

        Schema::dropIfExists('lms_quiz_user_answers');
        Schema::dropIfExists('lms_quiz_user_results');
    }

    public function down(): void
    {
        // Irreversible in one step: dropped tables/columns are not recreated here.
        // Restore from backup if this migration needs to be rolled back.
    }
};
