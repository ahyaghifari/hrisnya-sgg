<?php

namespace App\Jobs;

use App\Repositories\LMS\CourseRepositories;
use App\Repositories\LMS\MeetingUserRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateMeetingUsersJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected $courseUser;

    public function __construct($courseUser)
    {
        $this->courseUser = $courseUser;
    }

    public function handle()
    {
        // resolve repository via container supaya queue worker bisa menggunakannya
        $courseRepositories = app(CourseRepositories::class);
        $meetingUserRepository = app(MeetingUserRepository::class);

        $meetings = $courseRepositories->getMeetingByCourseId($this->courseUser->course_id);

        foreach ($meetings as $meeting) {
            if ($meetingUserRepository->findByUserIdAndMeetingId($this->courseUser->user_id, $meeting->id)) {
                continue;
            }

            $meetingUserRepository->createMeetingUser([
                'user_id' => $this->courseUser->user_id,
                'course_id' => $this->courseUser->course_id,
                'meeting_id' => $meeting->id,
                'is_attended' => false,
                'is_present' => false,
                'quiz_completed' => false,
                'quiz_score' => 0,
                'is_completed' => false,
                'completed_at' => null,
            ]);
        }
    }
}
