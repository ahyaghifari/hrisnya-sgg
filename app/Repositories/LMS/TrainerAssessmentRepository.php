<?php

namespace App\Repositories\LMS;

use App\Models\LMS\LmsCourses;
use App\Models\LMS\Trainer\TrainerAssessments;
use App\Models\LMS\Trainer\TrainerAssesmentsResults;
use App\Models\LMS\Trainer\TrainerScoreRanges;

class TrainerAssessmentRepository
{
    public function getMeetingIdsByCourseId(string $courseId): array
    {
        $course = LmsCourses::with('meetings')->find($courseId);
        
        if (!$course) {
            return [];
        }

        return $course->meetings->pluck('id')->toArray();
    }

    public function getTrainerAssessmentsByMeetingIds(array $meetingIds): \Illuminate\Database\Eloquent\Collection
    {
        return TrainerAssessments::whereIn('meeting_id', $meetingIds)->get();
    }

    public function getScoreRangeByTypeAndScore(string $type, float $score): ?TrainerScoreRanges
    {
        return TrainerScoreRanges::where('type', $type)
            ->where('min_score', '<=', $score)
            ->where('max_score', '>=', $score)
            ->first();
    }

    public function createOrUpdateResult(string $courseId, string $trainerId, array $data): TrainerAssesmentsResults
    {
        return TrainerAssesmentsResults::updateOrCreate(
            [
                'course_id' => $courseId,
                'trainer_id' => $trainerId,
            ],
            $data
        );
    }

    public function getTrainerIdFromCourse(string $courseId): ?string
    {
        $course = LmsCourses::with('meetings')->find($courseId);
        
        if (!$course || $course->meetings->isEmpty()) {
            return null;
        }

        return $course->meetings->first()->trainer_id;
    }
}
