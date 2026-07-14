<?php

namespace App\Http\Controllers\Api\LMS;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\LMS\CourseDetailsResource;
use App\Http\Resources\LMS\CoursesResource;
use App\Services\LMS\CourseUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseUserController extends Controller
{
    public function __construct(
        protected CourseUserService $courseUserService
    ) {}

    public function assignUser($courseId)
    {
        try {
            $data = $this->courseUserService->assignUserToCourse($courseId);

            if (!$data) {
                return ApiResponseHelper::error('Course not found or already enrolled', 400);
            }

            return ApiResponseHelper::success(new CourseDetailsResource($data->course), 'Followed course successfully');

        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred during password change', 500, $e->getMessage());
        }
    }

    public function getFollowedCourses(Request $request)
    {
        try {
            $userId = auth('sanctum')->id();
            $filters = $request->only(['type']);

            $courses = $this->courseUserService->getFollowedCoursesByUserId($userId, $filters);

            if ($courses->isEmpty()) {
                return ApiResponseHelper::notFound('No followed courses found');
            }

            return ApiResponseHelper::success(CoursesResource::collection($courses),
                'Retrieved followed courses successfully'
            );

        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred while retrieving followed courses', 500, $e->getMessage());
        }
    }

    public function downloadCertificate($courseUserId)
    {
        try {
            $userId = auth('sanctum')->id();
            $certificateUrl = $this->courseUserService->getDownloadableCertificateUrl($userId, $courseUserId);

            if (!$certificateUrl) {
                return ApiResponseHelper::forbidden('Certificate not available for download — you have not passed this course yet');
            }

            $filePath = storage_path('app/public/' . $certificateUrl);
            
            if (!file_exists($filePath)) {
                return ApiResponseHelper::notFound('Certificate file not found');
            }

            return response()->download($filePath);

        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred while retrieving the certificate', 500, $e->getMessage());
        }
    }
}

