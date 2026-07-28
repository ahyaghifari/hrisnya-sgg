<?php

namespace App\Listeners;

use App\Enum\CourseUserStatus;
use App\Events\LmsProgresUpdated;
use App\Repositories\LMS\CourseRepository;
use App\Services\LMS\CourseService;
use App\Services\LMS\CourseUserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LmsProgresListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected \App\Repositories\LMS\QuizRepository $quizRepo,
        protected CourseRepository $courseRepositories,
        protected CourseService $courseService,
        protected CourseUserService $courseUserService
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
            
            $courseUser->save();
        }
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

    private function checkCourseCompletion($courseUser, int $totalScore): string
    {
        $meetingUsers = $courseUser->meetingUsers;
        
        $allCompleted = $meetingUsers->every(function ($meetingUser) {
            return $meetingUser->is_completed === 1;
        });

        if ($allCompleted) {
            if (is_null($courseUser->completed_at)) {
                $courseUser->completed_at = now();
            }

            $isePassed = $this->checkUserIsPassed($courseUser->course, $totalScore);

            // "archived_at" jadi satu-satunya penanda "sudah pernah diproses selesai" — dulu
            // dicek lewat ada/tidaknya baris LmsUserHistories terpisah (lihat migration
            // consolidate_lms_user_histories_into_lms_course_users).
            if (is_null($courseUser->archived_at)) {
                $this->courseUserService->updateByUserIdAndCourseId(
                    $courseUser->user_id,
                    $courseUser->course_id,
                    [
                        'is_passed' => $isePassed,
                        'passed_at' => $isePassed ? now() : null,
                        'archived_at' => now(),
                    ]
                );
            }

            return CourseUserStatus::COMPLETED->value;
        } else {
            $courseUser->completed_at = null;
            
            if ($courseUser->status === CourseUserStatus::REQUESTED->value) {
                return CourseUserStatus::REQUESTED->value;
            }

            return CourseUserStatus::IN_PROGRESS->value;
        }
    }

    public function checkUserIsPassed($course, int $score): bool
    {
        $passingScore = $course->minimum_score ?? 70;
        return $score >= $passingScore;
    }
}
