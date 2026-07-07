<?php

namespace App\Services\Attendance;

use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\User;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Repositories\Attendance\AttendanceReqRepository;
use App\Repositories\Attendance\LeaveReqRepository;
use App\Repositories\Attendance\OvertimeReqRepository;
use App\Repositories\Attendance\PermissionReqRepository;
use App\Repositories\User\UserRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Notification\NotificationService;

class AttendaceReqService
{
    public function __construct(
        protected AttendanceReqRepository $attendanceReqRepository,
        protected OvertimeReqRepository $overtimeReqRepository,
        protected LeaveReqRepository $leaveReqRepository,
        protected PermissionReqRepository $permissionReqRepository,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected NotificationService $notificationService,
    ) {}

    public function getMasterAttendanceReqTypes(string $type, ?string $companyId = null)
    {
        return $this->attendanceReqRepository->getMasterAttendanceReqTypes($type, $companyId);
    }

    public function getListAttendanceRequestsByUserId(string $userId, array $filters = [])
    {
        return $this->attendanceReqRepository->getListAttendanceRequestsByUserId($userId, $filters);
    }

    public function findAttendanceRequestById(string $id): object|null
    {
        return $this->attendanceReqRepository->findAttendanceRequestById($id);
    }

    public function getLeaveUserBalance(string $userId, string $typeId): array
    {
        return $this->leaveReqRepository->getLeaveUserBalance($userId, $typeId);
    }

    public function storeOvertimeRequest(array $data, ?User $actingUser = null): object
    {
        $actingUser ??= auth('sanctum')->user();
        $userId = $actingUser->id;
        $type = 'overtime';
        $status = 'in_review';

        $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
            moduleType: 'attendance_request',
            companyId: $actingUser->company_id,
            userId: $userId
        );

        if (!$masterFlow) {
            throw new \Exception('No active approval flow found for attendance requests for this company.');
        }

        $request = $this->attendanceReqRepository->storeAttendanceRequest([
            'user_id' => $userId,
            'type' => $type,
            'status' => $status,
        ]);

        $overtimeType = $this->overtimeReqRepository->getOvertimeTypeById($data['type_id']);

        $uploadedFiles = $data['attachments'] ?? null;

        $overtimeData = [
            'request_id' => $request->id,
            'overtime_type_id' => $overtimeType?->id,
            'overtime_type' => $overtimeType?->name,
            'date' => $data['date'] ?? null,
            'tasks' => $data['tasks'] ?? null,
            'attachments_files' => $uploadedFiles,
            'task_description' => $data['task_description'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
        ];

        $this->overtimeReqRepository->storeOvertimeRequest($overtimeData);

        // Create approval snapshot
        $snapshot = $this->approvalCreationService->createSnapshot(
            moduleType: 'attendance_request',
            moduleId: $request->id,
            flow: $masterFlow,
            createdBy: $userId,
            companyId: $actingUser->company_id,
            userId: $userId
        );

        // Send notification to first approver
        $firstApprover = $this->notificationService->getFirstApproverFromSnapshot($snapshot);
        if ($firstApprover) {
            $this->notificationService->sendOvertimeRequestNotification(
                approver: $firstApprover,
                requestData: [
                    'request_id' => $request->id,
                    'overtime_type' => $overtimeType?->name,
                    'date' => $data['date'] ?? null,
                    'start_time' => $data['start_time'] ?? null,
                    'end_time' => $data['end_time'] ?? null,
                ],
                requesterName: $actingUser->name
            );
        }

        return $this->overtimeReqRepository->findAttendanceReqOvertimeDetail($request->id);
    }

    public function storeLeaveRequest(array $data, ?User $actingUser = null): object
    {
        $actingUser ??= auth('sanctum')->user();
        $userId = $actingUser->id;
        $type = 'leave';
        $status = 'in_review';

        $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
            moduleType: 'attendance_request',
            companyId: $actingUser->company_id,
            userId: $userId
        );

        if (!$masterFlow) {
            throw new \Exception('No active approval flow found for attendance requests for this company.');
        }

        $request = $this->attendanceReqRepository->storeAttendanceRequest([
            'user_id' => $userId,
            'type' => $type,
            'status' => $status,
        ]);

        $leaveType = $this->leaveReqRepository->getLeaveTypeById($data['type_id']);

        $leaveData = [
            'user_id' => $userId,
            'request_id' => $request->id,
            'type_id' => $data['type_id'],
            'leave_type' => $leaveType?->name,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
        ];

        $this->leaveReqRepository->storeLeaveRequest($leaveData);

        // Create approval snapshot
        $snapshot = $this->approvalCreationService->createSnapshot(
            moduleType: 'attendance_request',
            moduleId: $request->id,
            flow: $masterFlow,
            createdBy: $userId,
            companyId: $actingUser->company_id,
            userId: $userId
        );

        // Send notification to first approver
        $firstApprover = $this->notificationService->getFirstApproverFromSnapshot($snapshot);
        if ($firstApprover) {
            $this->notificationService->sendLeaveRequestNotification(
                approver: $firstApprover,
                requestData: [
                    'request_id' => $request->id,
                    'leave_type' => $leaveType?->name,
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'] ?? null,
                ],
                requesterName: $actingUser->name
            );
        }

        return $this->leaveReqRepository->findAttendanceReqLeaveDetail($request->id);
    }

    public function storePermissionRequest(array $data, ?User $actingUser = null): object
    {
        $actingUser ??= auth('sanctum')->user();
        $userId = $actingUser->id;
        $type = 'permission';
        $status = 'in_review';

        $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
            moduleType: 'attendance_request',
            companyId: $actingUser->company_id,
            userId: $userId
        );

        if (!$masterFlow) {
            throw new \Exception('No active approval flow found for attendance requests for this company.');
        }

        $request = $this->attendanceReqRepository->storeAttendanceRequest([
            'user_id' => $userId,
            'type' => $type,
            'status' => $status,
        ]);

        $permissionType = $this->permissionReqRepository->getPermissionTypeById($data['type_id']);

        $permissionData = [
            'request_id' => $request->id,
            'permission_type_id' => $data['type_id'],
            'permission_type' => $permissionType?->name,
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'purpose' => $data['purpose'] ?? null,
            'location' => $data['location'] ?? null,
            'substitute_user_id' => $data['substitute_user_id'] ?? null,
            'substitute_user_name' => $data['substitute_user_name'] ?? null,
            'substitute_task' => $data['substitute_task'] ?? null,
        ];

        $this->permissionReqRepository->storePermissionRequest($permissionData);

        // Create approval snapshot
        $snapshot = $this->approvalCreationService->createSnapshot(
            moduleType: 'attendance_request',
            moduleId: $request->id,
            flow: $masterFlow,
            createdBy: $userId,
            companyId: $actingUser->company_id,
            userId: $userId
        );

        // Send notification to first approver
        $firstApprover = $this->notificationService->getFirstApproverFromSnapshot($snapshot);
        if ($firstApprover) {
            $this->notificationService->sendPermissionRequestNotification(
                approver: $firstApprover,
                requestData: [
                    'request_id' => $request->id,
                    'permission_type' => $permissionType?->name,
                    'date' => $data['date'],
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'purpose' => $data['purpose'] ?? null,
                ],
                requesterName: $actingUser->name
            );
        }

        return $this->permissionReqRepository->findAttendanceReqPermissionDetail($request->id);
    }
}
