<?php

namespace App\Http\Controllers\Api\LMS;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\MeetingAttendanceService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        protected MeetingAttendanceService $attendanceService
    ) {}

    public function clockIn(string $meetingId)
    {
        try {
            $response = $this->attendanceService->clockIn($meetingId);
            
            if (isset($response['success']) && $response['success'] === false) {
                return ApiResponseHelper::error($response['message'], 400);
            }

            return ApiResponseHelper::success(null, 'Clock-in successful.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred while processing your request.', 500, $e->getMessage());
        }
    }
}
