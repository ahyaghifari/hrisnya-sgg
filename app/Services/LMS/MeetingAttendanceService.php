<?php

namespace App\Services\LMS;

use App\Enum\AttendanceStatus;
use App\Events\LmsProgresUpdated;
use App\Repositories\LMS\CourseRepositories;
use App\Repositories\LMS\MeetingAttendanceRepository;
use App\Repositories\LMS\MeetingUserRepository;
use Carbon\Carbon;

class MeetingAttendanceService
{
    public function __construct(
        protected MeetingAttendanceRepository $meetingAttendanceRepository,
        protected MeetingUserRepository $meetingUserRepository,
        protected CourseRepositories $courseRepositories
    ) {}

    public function clockIn(string $meetingId)
    {
        $userId = auth('sanctum')->id();

        $meeting = $this->courseRepositories->getMeetingById($meetingId);
        $existing = $this->meetingAttendanceRepository->findByMeetingAndUser($meetingId, $userId);
        $meetingUser = $this->meetingUserRepository->findByUserIdAndMeetingId($userId, $meetingId);

        if ($existing) {
            return [
                'success' => false,
                'message' => 'You have already checked in for this meeting.',
                'data' => $existing,
            ];
        }

        $now = Carbon::now();
        $startDateTime = Carbon::parse($meeting->start_date . ' ' . $meeting->start_time);
        $endDateTime = Carbon::parse($meeting->end_date . ' ' . $meeting->end_time);

        if ($now->lt($startDateTime) || $now->gt($endDateTime)) {
            $attendance = $this->meetingAttendanceRepository->create([
                'meeting_id' => $meetingId,
                'user_id' => $userId,
                'checked_in_at' => $now,
                'status' => AttendanceStatus::LATE->value,
            ]);

            $dataMeetingUser = [
                'is_attended' => true,
                'is_present' => false
            ];

            $this->meetingUserRepository->updateByUserIdAndMeetingId($userId, $meetingId, $dataMeetingUser);
            event(new LmsProgresUpdated(auth('sanctum')->user(), $this->courseRepositories->getCourseById($meeting->course_id)));

            return [
                'success' => true,
                'message' => 'Clock-in recorded, but you are late.',
                'data' => $attendance,
            ];
        }

        $attendance = $this->meetingAttendanceRepository->create([
            'meeting_id' => $meetingId,
            'user_id' => $userId,
            'checked_in_at' => $now,
            'status' => AttendanceStatus::PRESENT->value,
        ]);

        $meetingUser = $this->meetingUserRepository->findByUserIdAndMeetingId($userId, $meetingId);

        $dataMeetingUser = [
            'is_attended' => true,
            'is_present' => true
        ];

        if ($meetingUser && isset($meetingUser->quiz_completed) && $meetingUser->quiz_completed) {
            $dataMeetingUser['is_completed'] = true;
            $dataMeetingUser['completed_at'] = now();
        }

        $this->meetingUserRepository->updateByUserIdAndMeetingId($userId, $meetingId, $dataMeetingUser);
        event(new LmsProgresUpdated(auth('sanctum')->user(), $this->courseRepositories->getCourseById($meeting->course_id)));

        return [
            'success' => true,
            'message' => 'Clock-in recorded successfully.',
            'data' => $attendance,
        ];
    }
}
