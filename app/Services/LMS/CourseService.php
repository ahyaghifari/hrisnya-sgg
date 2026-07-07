<?php

namespace App\Services\LMS;

use App\Events\LmsProgresUpdated;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\LMS\LmsCoursePlanItems;
use App\Models\LMS\LmsRankings;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Repositories\LMS\CourseRepositories;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Approval\ApprovalFlowService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CourseService
{
    public function __construct(
        protected CourseRepositories $courseRepositories,
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
}
