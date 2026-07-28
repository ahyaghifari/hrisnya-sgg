<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_quiz_results', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_quiz_id')->constrained('lms_meeting_quizzes')->cascadeOnDelete();
            $table->foreignUlid('quiz_id')->constrained('lms_quizzes')->cascadeOnDelete();
            $table->foreignUlid('respondent_id')->constrained('lms_respondents')->cascadeOnDelete();
            $table->integer('total_score')->default(0);
            $table->integer('max_score')->default(0);
            $table->integer('correct_count')->default(0);
            $table->integer('wrong_count')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('completed');
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['meeting_quiz_id', 'respondent_id']);
        });

        Schema::create('lms_quiz_answers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_quiz_id')->constrained('lms_meeting_quizzes')->cascadeOnDelete();
            $table->foreignUlid('quiz_id')->constrained('lms_quizzes')->cascadeOnDelete();
            $table->foreignUlid('question_id')->constrained('lms_question_banks')->cascadeOnDelete();
            $table->foreignUlid('respondent_id')->constrained('lms_respondents')->cascadeOnDelete();
            $table->string('user_answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->integer('earned_points')->default(0);
            $table->timestamps();

            $table->unique(['meeting_quiz_id', 'question_id', 'respondent_id'], 'lms_quiz_answers_mq_q_respondent_unique');
        });

        $this->copyData();
    }

    private function copyData(): void
    {
        $respondentLookup = function (string $courseId, string $userId) {
            static $cache = [];
            $key = $courseId . '|' . $userId;

            if (! array_key_exists($key, $cache)) {
                $cache[$key] = DB::table('lms_respondents')
                    ->where('course_id', $courseId)
                    ->where('user_id', $userId)
                    ->value('id');
            }

            return $cache[$key];
        };

        $meetingQuizCourse = DB::table('lms_meeting_quizzes as mq')
            ->join('lms_meetings as m', 'm.id', '=', 'mq.meeting_id')
            ->pluck('m.course_id', 'mq.id');

        $skipped = [];

        foreach (DB::table('lms_quiz_user_results')->orderBy('id')->cursor() as $row) {
            $courseId = $meetingQuizCourse[$row->meeting_quiz_id] ?? null;
            $respondentId = $courseId ? $respondentLookup($courseId, $row->user_id) : null;

            if (! $respondentId) {
                $skipped[] = "result {$row->id}";
                continue;
            }

            DB::table('lms_quiz_results')->insert([
                'id' => $row->id,
                'meeting_quiz_id' => $row->meeting_quiz_id,
                'quiz_id' => $row->quiz_id,
                'respondent_id' => $respondentId,
                'total_score' => $row->total_score,
                'max_score' => $row->max_score,
                'correct_count' => $row->correct_count,
                'wrong_count' => $row->wrong_count,
                'is_completed' => $row->is_completed,
                'completed_at' => $row->completed_at,
                'status' => $row->status,
                'reviewed_by' => $row->reviewed_by,
                'reviewed_at' => $row->reviewed_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        foreach (DB::table('lms_quiz_user_answers')->orderBy('id')->cursor() as $row) {
            $courseId = $meetingQuizCourse[$row->meeting_quiz_id] ?? null;
            $respondentId = $courseId ? $respondentLookup($courseId, $row->user_id) : null;

            if (! $respondentId) {
                $skipped[] = "answer {$row->id}";
                continue;
            }

            DB::table('lms_quiz_answers')->insert([
                'id' => $row->id,
                'meeting_quiz_id' => $row->meeting_quiz_id,
                'quiz_id' => $row->quiz_id,
                'question_id' => $row->question_id,
                'respondent_id' => $respondentId,
                'user_answer' => $row->user_answer,
                'is_correct' => $row->is_correct,
                'earned_points' => $row->earned_points,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        $oldResults = DB::table('lms_quiz_user_results')->count();
        $newResults = DB::table('lms_quiz_results')->count();
        $oldAnswers = DB::table('lms_quiz_user_answers')->count();
        $newAnswers = DB::table('lms_quiz_answers')->count();

        if ($oldResults !== $newResults || $oldAnswers !== $newAnswers) {
            throw new \RuntimeException(sprintf(
                'lms_quiz_answers/results row count mismatch after copy (results %d->%d, answers %d->%d). Skipped: %s',
                $oldResults,
                $newResults,
                $oldAnswers,
                $newAnswers,
                implode(', ', $skipped) ?: 'none'
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_quiz_answers');
        Schema::dropIfExists('lms_quiz_results');
    }
};
