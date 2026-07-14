<?php

namespace App\Services\LMS;

use App\Events\LmsProgresUpdated;
use App\Models\LMS\LmsCoursePlanItems;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsRankings;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Repositories\LMS\CourseRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Approval\ApprovalFlowService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CourseService
{
    public function __construct(
        protected CourseRepository $courseRepositories,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected ApprovalFlowService $approvalFlowService,
    ) {}

    public function getAllCategories()
    {
        return $this->courseRepositories->getAllCategories();
    }

    public function getAllCourse(?string $keyword = null, ?string $category = null, int $limit = 10)
    {
        return $this->courseRepositories->getAllCourse($keyword, $category, $limit);
    }

    public function getCourseById(string $id)
    {
        $course = $this->courseRepositories->getCourseById($id);

        if (!$course) {
            return null;
        }

        // event(new LmsProgresUpdated(auth('sanctum')->user(), $course));
        return $course;
    }

    public function storeFeedback(array $data)
    {
        return $this->courseRepositories->storeFeedback($data);
    }

    public function updateRanking(string $userId, string $courseId, int $score): void
    {
        DB::transaction(function () use ($userId, $courseId, $score) {
            $rankData = [
                'course_id' => $courseId,
                'user_id' => $userId,
                'total_score' => $score,
                'calculated_at' => now(),
            ];
            
            $this->courseRepositories->upsertCourseRanking($rankData);

            $this->recalculateCourseRanking($courseId);
        });
    }
    
    protected function recalculateCourseRanking(string $courseId): void
    {
        $rankings = LmsRankings::where('course_id', $courseId)
            ->orderByDesc('total_score')
            ->get();

        foreach ($rankings as $index => $ranking) {
            $ranking->update(['rank' => $index + 1]);
        }
    }

    public function getRanksByUserId(array $filters = [])
    {
        return $this->courseRepositories->getRanksByUserId($filters);
    }

    public function getSuggestionsKeywords(string $partialKeyword, int $limit = 5)
    {
        return $this->courseRepositories->getSuggestionsKeywords($partialKeyword, $limit);
    }

    public function requestCourse(LmsCoursePlanItems $coursePlanItem)
    {
        $userId = Auth::id();

        $masterFlow = $this->approvalSnapshotRepository->findMasterApprovalFlowByCompany(
            moduleType: 'lms_course_request',
            companyId: Auth::user()->company_id
        );

        if (!$masterFlow) {
            throw new \Exception('No active approval flow found for LMS course requests for this company.');
        }

        $this->approvalCreationService->createSnapshot(
            moduleType: 'lms_course_request',
            moduleId: $coursePlanItem->id,
            flow: $masterFlow,
            createdBy: $userId,
            companyId: Auth::user()->company_id,
            userId: $userId
        );

        return $coursePlanItem->refresh();
    }

    public function approveCourseRequest(
        LmsCoursePlanItems $coursePlanItem,
        string $status,
        string $note
    ): LmsCoursePlanItems
    {
        $userId = Auth::id();

        $this->approvalFlowService->advanceStep(
            snapshot: $coursePlanItem->approvalSnapshot,
            actorId: $userId,
            note: $note,
            action: $status,
        );

        $this->courseRepositories->updateCourseRequest($coursePlanItem, [
            'request_status' => $status,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $coursePlanItem->refresh();
    }

    /**
     * Duplikat course jadi batch baru — menggantikan rencana "scheduler" otomatis (kolom
     * repeat_cycle/next_run_date tidak pernah punya job consumer, lihat
     * planning/2026-07-14-perbaikan-lms-dan-akses-portal.md Tahap 3 #4). Admin duplikat manual
     * course lama sebagai template, lalu sesuaikan tanggal meeting-nya di course baru.
     * Ikut disalin: meeting, materi, dan quiz yang terpasang ke tiap meeting (quiz bank-nya
     * sendiri tetap dipakai bersama, bukan digandakan — sama seperti desain aslinya).
     * TIDAK ikut disalin: peserta, kehadiran, hasil quiz, riwayat, feedback — course baru mulai
     * kosong dari sisi peserta.
     */
    public function duplicateCourse(LmsCourses $course): LmsCourses
    {
        return DB::transaction(function () use ($course) {
            $newCourse = $course->replicate();
            $newCourse->code = $course->code . '-' . now()->format('mdHis');
            $newCourse->title = $course->title . ' (Salinan)';
            $newCourse->created_by = Auth::id();
            $newCourse->save();

            foreach ($course->competencies as $competency) {
                $newCourse->competencies()->attach($competency->id);
            }

            foreach ($course->meetings()->with('materials', 'quizzes')->get() as $meeting) {
                $newMeeting = $meeting->replicate();
                $newMeeting->course_id = $newCourse->id;
                $newMeeting->save();

                foreach ($meeting->materials as $material) {
                    $newMaterial = $material->replicate();
                    $newMaterial->meeting_id = $newMeeting->id;
                    $newMaterial->save();
                }

                foreach ($meeting->quizzes as $meetingQuiz) {
                    $newMeetingQuiz = $meetingQuiz->replicate();
                    $newMeetingQuiz->meeting_id = $newMeeting->id;
                    $newMeetingQuiz->save();
                }
            }

            return $newCourse->fresh();
        });
    }
}
