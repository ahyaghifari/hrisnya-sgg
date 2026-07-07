<?php

namespace App\Services\LMS;

use App\Repositories\LMS\TrainerAssessmentRepository;
use App\Repositories\User\UserRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TrainerAssessmentService
{
    protected TrainerAssessmentRepository $repository;
    protected UserRepository $userRepository;
    
    public function __construct(
        TrainerAssessmentRepository $repository,
        UserRepository $userRepository
    )
    {
        $this->repository = $repository;
        $this->userRepository = $userRepository;
    }

    public function generateTrainerAssessmentResult(string $courseId): ?array
    {
        try {
            $meetingIds = $this->repository->getMeetingIdsByCourseId($courseId);
            
            if (empty($meetingIds)) {
                Log::info("No meetings found for course: {$courseId}");
                return null;
            }

            $trainerAssessments = $this->repository->getTrainerAssessmentsByMeetingIds($meetingIds);
            
            if ($trainerAssessments->isEmpty()) {
                Log::info("No trainer assessments found for meetings: " . implode(', ', $meetingIds));
                return null;
            }

            $totalMeetings = count($meetingIds);
            $totalScoreHonor = $trainerAssessments->sum('score_honor');
            $averageScoreHonor = $totalScoreHonor / $totalMeetings;

            $totalScoreValidity = $trainerAssessments->sum('score_validity');
            $averageScoreValidity = $totalScoreValidity / $totalMeetings;

            $honorRange = $this->repository->getScoreRangeByTypeAndScore('honor', $averageScoreHonor);
            $honorAmount = $honorRange ? $honorRange->honor_amount : 0;

            $validityRange = $this->repository->getScoreRangeByTypeAndScore('validity', $averageScoreValidity);
            $validityMonths = $validityRange ? $validityRange->validity_months : 0;

            $validUntil = null;
            if ($validityMonths > 0) {
                $validUntil = Carbon::now()->addMonths($validityMonths)->format('Y-m-d');
            }

            $trainerId = $this->repository->getTrainerIdFromCourse($courseId);
            
            if (!$trainerId) {
                Log::warning("No trainer found for course: {$courseId}");
                return null;
            }

            $result = $this->repository->createOrUpdateResult($courseId, $trainerId, [
                'company_id' => $this->userRepository->findById($trainerId)->company_id,
                'honor_amount' => $honorAmount,
                'validity_months' => $validityMonths,
                'valid_until' => $validUntil,
            ]);

            return [
                'success' => true,
                'data' => $result,
                'average_score_honor' => $averageScoreHonor,
                'average_score_validity' => $averageScoreValidity,
            ];

        } catch (\Exception $e) {
            Log::error("Error generating trainer assessment result: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
