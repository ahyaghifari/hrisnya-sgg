<?php

namespace App\Repositories\LMS;

use App\Models\LMS\LmsCourseMeetingUsers;

class MeetingUserRepository
{
    public function createMeetingUser(array $data)
    {
        LmsCourseMeetingUsers::create($data);
    }

    public function findByUserIdAndMeetingId($userId, $meetingId)
    {
        return LmsCourseMeetingUsers::where('user_id', $userId)
            ->where('meeting_id', $meetingId)
            ->first();
    }

    public function updateByUserIdAndMeetingId($userId, $meetingId, array $data)
    {
        return LmsCourseMeetingUsers::where('user_id', $userId)
            ->where('meeting_id', $meetingId)
            ->update($data);
    }
}
