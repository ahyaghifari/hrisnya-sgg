<?php

namespace App\Services\LMS;

use App\Enum\CourseUserStatus;
use App\Enum\CustomPermissionEnum;
use App\Models\LMS\LmsCourseUsers;
use App\Models\User;
use App\Notifications\CourseEnrollmentApproved;
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

    /**
     * Satu-satunya jalur untuk menyetujui pendaftaran (dipakai widget approval, halaman Peserta
     * di course, dan detail course) - supaya permission check dan notifikasi ke peserta
     * konsisten di semua tempat, bukan diduplikasi per entry point.
     */
    public function approveEnrollment(LmsCourseUsers $courseUser, User $actor): void
    {
        abort_unless($actor->can(CustomPermissionEnum::APPROVE_PENGAJUAN_PELATIHAN->value), 403, 'Anda tidak punya izin approve pengajuan pelatihan.');

        $courseUser->update(['status' => CourseUserStatus::APPROVED->value]);

        $courseUser->user?->notify(new CourseEnrollmentApproved($courseUser->course));
    }

    public function rejectEnrollment(LmsCourseUsers $courseUser, User $actor): void
    {
        abort_unless($actor->can(CustomPermissionEnum::APPROVE_PENGAJUAN_PELATIHAN->value), 403, 'Anda tidak punya izin approve pengajuan pelatihan.');

        $courseUser->update(['status' => CourseUserStatus::CANCELED->value]);
    }
}
