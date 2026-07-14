<?php

namespace App\Services\LMS;

use App\Repositories\LMS\CourseRepository;
use App\Repositories\LMS\CourseUserRepository;

class CourseUserService
{
    public function __construct(
        protected CourseUserRepository $courseUserRepository,
        protected CourseRepository $courseRepositories
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

    /**
     * Sertifikat cuma bisa diunduh peserta sendiri kalau is_passed = true — admin tetap boleh
     * mengunggah file-nya kapan saja course COMPLETED (lihat ParticipantCourses::upload_certificate),
     * tapi peserta yang belum lulus tidak bisa mengunduhnya.
     */
    public function getDownloadableCertificateUrl(string $userId, string $courseUserId): ?string
    {
        $history = $this->courseUserRepository->getCertificateHistory($userId, $courseUserId);

        if (!$history || !$history->certificate_url || !$history->is_passed) {
            return null;
        }

        return $history->certificate_url;
    }
}
