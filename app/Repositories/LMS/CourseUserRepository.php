<?php

namespace App\Repositories\LMS;

use App\Enum\CourseUserStatus;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;

class CourseUserRepository
{
    public function assignUserToCourse($userId, $courseId)
    {
        return LmsCourseUsers::create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'status' => CourseUserStatus::REQUESTED->value,
            'progress' => 0,
            'score' => 0,
            'ranking' => null,
            'enrolled_at' => now(),
            'completed_at' => null,
        ]);
    }

    public function getByUserIdAndCourseId($userId, $courseId)
    {
        return LmsCourseUsers::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();
    }

    public function findById($id)
    {
        return LmsCourseUsers::where('course_id', $id)->first();
    }

    public function isUserEnrolledInCourse($userId, $courseId)
    {
        return LmsCourseUsers::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->exists();
    }

    public function updateByUserIdAndCourseId($userId, $courseId, array $data)
    {
        return LmsCourseUsers::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->update($data);
    }

    public function getFollowedCoursesByUserId(string $userId, array $filters = [])
    {
        if (isset($filters['type']) && $filters['type'] === 'completed') {
            // "Selesai & sudah diarsipkan" — dulu dicek lewat relasi LmsUserHistories terpisah,
            // sekarang cukup archived_at di lms_course_users sendiri (lihat migration
            // consolidate_lms_user_histories_into_lms_course_users).
            return LmsCourses::whereHas('courseUsers', function ($query) use ($userId) {
                $query->where('user_id', $userId)->whereNotNull('archived_at');
            })->get();
        }

        return LmsCourses::whereHas('courseUsers', function ($query) use ($userId, $filters) {
            $query->where('user_id', $userId);

            if (isset($filters['type'])) {
                if ($filters['type'] === 'ongoing') {
                    $query->whereNull('completed_at');
                }
            }
        })->get();
    }

    public function getCertificateHistory(string $userId, string $courseUserId)
    {
        return LmsCourseUsers::where('user_id', $userId)
            ->where('course_id', $courseUserId)
            ->first();
    }
}
