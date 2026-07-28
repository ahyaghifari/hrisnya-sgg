<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $pairs = collect();

        DB::table('lms_quiz_user_results as r')
            ->join('lms_meeting_quizzes as mq', 'mq.id', '=', 'r.meeting_quiz_id')
            ->join('lms_meetings as m', 'm.id', '=', 'mq.meeting_id')
            ->select('m.course_id', 'r.user_id')
            ->distinct()
            ->get()
            ->each(fn ($row) => $pairs->push([$row->course_id, $row->user_id]));

        DB::table('lms_quiz_user_answers as a')
            ->join('lms_meeting_quizzes as mq', 'mq.id', '=', 'a.meeting_quiz_id')
            ->join('lms_meetings as m', 'm.id', '=', 'mq.meeting_id')
            ->select('m.course_id', 'a.user_id')
            ->distinct()
            ->get()
            ->each(fn ($row) => $pairs->push([$row->course_id, $row->user_id]));

        DB::table('lms_course_feedbacks')
            ->select('course_id', 'user_id')
            ->distinct()
            ->get()
            ->each(fn ($row) => $pairs->push([$row->course_id, $row->user_id]));

        $pairs = $pairs->unique(fn ($p) => $p[0] . '|' . $p[1]);

        $users = DB::table('users')->whereIn('id', $pairs->pluck(1)->unique())
            ->select('id', 'name', 'nip', 'company_id')
            ->get()
            ->keyBy('id');

        $now = now();

        foreach ($pairs as [$courseId, $userId]) {
            $user = $users->get($userId);

            if (! $user) {
                continue;
            }

            $exists = DB::table('lms_respondents')
                ->where('course_id', $courseId)
                ->where('user_id', $userId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('lms_respondents')->insert([
                'id' => (string) Str::ulid(),
                'course_id' => $courseId,
                'user_id' => $userId,
                'name' => $user->name,
                'nip' => $user->nip,
                'company_id' => $user->company_id,
                'unit' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Backfilled data is derived; nothing to reverse independently of the
        // parent table drop in create_lms_respondents_table's down().
    }
};
