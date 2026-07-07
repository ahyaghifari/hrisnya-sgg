<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\LeaveRequest;
use App\Http\Requests\Attendance\OvertimeRequest;
use App\Http\Resources\Attendance\DetailReqResource;
use App\Http\Resources\Attendance\LeaveReqResource;
use App\Http\Resources\Attendance\ListReqResource;
use App\Http\Resources\Attendance\PermissionReqResource;
use App\Http\Resources\Attendance\ReqOvertimeResource;
use App\Http\Resources\Attendance\RequestTypeResource;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Http\Request;

class AttendanceRequestController extends Controller
{
    public function __construct(
        protected AttendaceReqService $attendaceReqService
    ) {}

    public function getMasterAttendanceReqTypes(Request $request)
    {
        try {
            $type = $request->query('type');
            $data = $this->attendaceReqService->getMasterAttendanceReqTypes($type);

            return ApiResponseHelper::success(RequestTypeResource::collection($data), 'Successfully retrieved attendance request types.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to retrieve attendance request types.',
                500,
                $e->getMessage()
            );
        }
    }

    public function getListAttendanceRequestsByUserId(Request $request)
    {
        try {
            $userId = auth('sanctum')->user()->id;
            $filters = $request->query();
            $data = $this->attendaceReqService->getListAttendanceRequestsByUserId($userId, $filters);

            if ($data->isEmpty()) {
                return ApiResponseHelper::notFound('No attendance requests found for the user.');
            }

            return ApiResponseHelper::success(ListReqResource::collection($data), 'Successfully retrieved attendance requests.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to retrieve attendance requests.',
                500,
                $e->getMessage()
            );
        }
    }

    public function findAttendanceRequestById(string $id): object|null
    {
        try {
            $data = $this->attendaceReqService->findAttendanceRequestById($id);

            if (!$data) {
                return ApiResponseHelper::notFound('Attendance request not found.');
            }

            return ApiResponseHelper::success(new DetailReqResource($data), 'Successfully retrieved attendance request.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to retrieve attendance request.',
                500,
                $e->getMessage()
            );
        }
    }

    public function getLeaveUserBalance(string $typeId)
    {
        try {
            $userId = auth('sanctum')->user()->id;
            $balance = $this->attendaceReqService->getLeaveUserBalance($userId, $typeId);

            if ($balance === null) {
                return ApiResponseHelper::notFound('Leave balance not found.');
            }

            return ApiResponseHelper::success($balance, 'Successfully retrieved leave balance.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to retrieve leave balance.',
                500,
                $e->getMessage()
            );
        }
    }

    public function storeOvertimeRequest(OvertimeRequest $request)
    {
        try {
            $data = $request->all();
            $overtimeRequest = $this->attendaceReqService->storeOvertimeRequest($data);

            return ApiResponseHelper::success(new ReqOvertimeResource($overtimeRequest), 'Overtime request submitted successfully.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to submit overtime request. Please ensure you have an active approval flow for attendance requests and try again.',
                500,
                $e->getMessage()
            );
        }
    }

    public function storeLeaveRequest(LeaveRequest $request)
    {
        try {
            $data = $request->all();
            $leaveRequest = $this->attendaceReqService->storeLeaveRequest($data);

            return ApiResponseHelper::success(new LeaveReqResource($leaveRequest), 'Leave request submitted successfully.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to submit leave request. Please ensure you have an active approval flow for leave requests and try again.',
                500,
                $e->getMessage()
            );
        }
    }

    public function storePermissionRequest(Request $request)
    {
        try {
            $data = $request->all();
            $permissionRequest = $this->attendaceReqService->storePermissionRequest($data);

            return ApiResponseHelper::success(new PermissionReqResource($permissionRequest), 'Permission request submitted successfully.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to submit permission request. Please ensure you have an active approval flow for permission requests and try again.',
                500,
                $e->getMessage()
            );
        }
    }
}
