<?php

namespace App\Repositories\LMS;

use App\Models\LMS\LmsMeetingAttendances;

class MeetingAttendanceRepository
{
    public function findByMeetingAndUser(string $meetingId, string $userId)
    {
        return LmsMeetingAttendances::where('meeting_id', $meetingId)
            ->where('user_id', $userId)
            ->first();
    }

    public function create(array $data)
    {
        return LmsMeetingAttendances::create($data);
    }

    public function update(LmsMeetingAttendances $attendance, array $data)
    {
        $attendance->update($data);
        return $attendance;
    }
}
