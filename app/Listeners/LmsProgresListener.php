<?php

namespace App\Listeners;

use App\Enum\CourseUserStatus;
use App\Events\LmsProgresUpdated;
use App\Models\LMS\LmsMeetingQuizzes;
use App\Models\LMS\LmsQuizResults;
use App\Repositories\LMS\CourseRepository;
use App\Services\LMS\CourseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LmsProgresListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected \App\Repositories\LMS\QuizRepository $quizRepo,
        protected CourseRepository $courseRepositories,
        protected CourseService $courseService
    ) {}
    
    public function handle(LmsProgresUpdated $event): void
    {
        $user = $event->user;
        $course = $event->course;

        $courseUser = $course->courseUsers()->where('user_id', $user->id)->first();

        if ($courseUser) {
            $meetingUsers = $courseUser->meetingUsers;
            $totalMeetings = $course->meetings()->count();
            $totalComponents = $totalMeetings * 2;

            $completedComponents = 0;

            foreach ($meetingUsers as $mu) {
                if ($mu->is_attended) {
                    $completedComponents++;
                }
                if ($mu->quiz_completed) {
                    $completedComponents++;
                }
            }

            $progress = $totalComponents > 0 ? ($completedComponents / $totalComponents) * 100 : 0;
            $courseUser->progress = $progress;

            $totalScore = $this->countTotalScore($courseUser);
            $courseUser->score = $totalScore;
            
            // Update course user status based on completion
            $courseUser->status = $this->checkCourseCompletion($courseUser, $totalScore);

            $this->syncPassed($courseUser, $course, $user->id);

            $courseUser->save();
        }
    }

    /**
     * Kelulusan = semua kuis bertanda "syarat kelulusan" sudah selesai dinilai dan persentase
     * skornya >= minimum_score kuis itu sendiri. Tanpa kuis penentu / masih ada yang belum
     * dikerjakan atau menunggu review esai: is_passed tidak diubah. passed_at terisi saat
     * keputusan final (is_passed=false + passed_at terisi = selesai tapi tidak lulus, lihat
     * ParticipantCourses).
     */
    private function syncPassed($courseUser, $course, string $userId): void
    {
        $quizzes = LmsMeetingQuizzes::where('is_graduation_requirement', true)
            ->whereHas('meeting', fn ($q) => $q->where('course_id', $course->id))
            ->pluck('minimum_score', 'id');

        if ($quizzes->isEmpty()) {
            return;
        }

        $results = LmsQuizResults::whereIn('meeting_quiz_id', $quizzes->keys())
            ->where('status', 'completed')
            ->whereHas('respondent', fn ($q) => $q->where('user_id', $userId))
            ->get()
            ->keyBy('meeting_quiz_id');

        if ($results->count() < $quizzes->count()) {
            return;
        }

        $passed = $results->every(
            fn ($r) => $r->max_score > 0 && round($r->total_score / $r->max_score * 100) >= (int) $quizzes[$r->meeting_quiz_id]
        );

        $courseUser->is_passed = $passed;
        $courseUser->passed_at ??= now();
    }

    private function countTotalScore($courseUser): int
    {
        $meetingUsers = $courseUser->meetingUsers;
        $totalMeetings = count($meetingUsers);
        if ($totalMeetings === 0) {
            return 0;
        }

        $quizScores = [];
        $totalQuizCount = 0;
        $attendedCount = 0;

        foreach ($meetingUsers as $mu) {
            $meetingQuizzes = $this->quizRepo->getMeetingQuizByMeetingId($mu->meeting_id);
            $quizCount = $meetingQuizzes ? $meetingQuizzes->count() : 0;
            $totalQuizCount += $quizCount;

            if (!is_null($mu->quiz_score)) {
                $quizScores[] = $mu->quiz_score * $quizCount;
            }

            if ($mu->is_present) {
                $attendedCount++;
            }
        }

        $totalQuizScore = array_sum($quizScores);
        $avgQuizScore = $totalQuizCount > 0 ? $totalQuizScore / $totalQuizCount : 0;

        $attendancePercent = ($attendedCount / $totalMeetings) * 100;

        $finalScore = ($avgQuizScore * 0.5) + ($attendancePercent * 0.5);

        return (int) round($finalScore);
    }

    /**
     * "Selesai" (completed_at + status COMPLETED) sengaja TIDAK dihitung otomatis di sini lagi —
     * absensi & quiz masih diisi manual oleh L&D lewat halaman pertemuan (absen_masuk/
     * absen_pulang), jadi is_completed per-meeting bukan sinyal yang bisa dipercaya. L&D
     * menandai selesai manual lewat CourseUserService::markCompleted().
     */
    private function checkCourseCompletion($courseUser, int $totalScore): string
    {
        if (in_array($courseUser->status, [CourseUserStatus::COMPLETED->value, CourseUserStatus::NOT_COMPLETED->value], true)) {
            return $courseUser->status;
        }

        if ($courseUser->status === CourseUserStatus::REQUESTED->value) {
            return CourseUserStatus::REQUESTED->value;
        }

        return CourseUserStatus::IN_PROGRESS->value;
    }
}
