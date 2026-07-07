<?php

namespace App\Services\LMS;

use App\Repositories\LMS\CourseRepositories;
use App\Repositories\LMS\CourseUserRepository;

class CourseUserService
{
    public function __construct(
        protected CourseUserRepository $courseUserRepository,
        protected CourseRepositories $courseRepositories
    ) {}

    public function assignUserToCourse($courseId)
    {
        $userId = auth('sanctum')->id();

        if ($this->courseRepositories->getCourseById($courseId) === null) {
            return null;
        }
        
        if ($this->courseUserRepository->isUserEnrolledInCourse($userId, $courseId)) {
            return null;
        }

        return $this->courseUserRepository->assignUserToCourse($userId, $courseId);
    }

    public function getFollowedCoursesByUserId(string $userId, array $filters = [])
    {
        return $this->courseUserRepository->getFollowedCoursesByUserId($userId, $filters);
    }

    public function updateByUserIdAndCourseId($userId, $courseId, array $data)
    {
        return $this->courseUserRepository->updateByUserIdAndCourseId($userId, $courseId, $data);
    }

    public function getCertificateUrl(string $userId, string $courseUserId)
    {
        return $this->courseUserRepository->getCertificateUrl($userId, $courseUserId);
    }
}
