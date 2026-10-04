<?php

namespace App\Services\Notification;

use App\Notifications\LeaveRequestSubmitted;
use App\Notifications\OvertimeRequestSubmitted;
use App\Notifications\PermissionRequestSubmitted;
use App\Models\User;
use App\Services\Identity\EmployeeContext;

class NotificationService
{
    public function sendOvertimeRequestNotification(
        User $approver,
        array $requestData,
        string $requesterName
    ): void {
        $approver->notify(new OvertimeRequestSubmitted($requestData, $requesterName));
    }

    public function sendLeaveRequestNotification(
        User $approver,
        array $requestData,
        string $requesterName
    ): void {
        $approver->notify(new LeaveRequestSubmitted($requestData, $requesterName));
    }

    public function sendPermissionRequestNotification(
        User $approver,
        array $requestData,
        string $requesterName
    ): void {
        $approver->notify(new PermissionRequestSubmitted($requestData, $requesterName));
    }

    public function getFirstApproverFromSnapshot($snapshot): ?User
    {
        if (!$snapshot || !$snapshot->activity) {
            return null;
        }

        $activity = $snapshot->activity;

        switch ($activity->approver_type) {
            case 'user':
                return User::find($activity->approver_ref_id);

            case 'role':
                // For role-based approval, get the first user with that role
                $roleId = $activity->approver_ref_id;
                $query = User::whereHas('roles', function ($q) use ($roleId) {
                    $q->where('id', $roleId);
                });

                // If scope is specific department, filter by department
                if ($activity->scope_type === 'specific_department') {
                    EmployeeContext::whereOrgStructure($query, $activity->scope_ref_id);
                }

                return $query->first();

            case 'job_position':
                return EmployeeContext::whereJobPositionIn(User::query(), [$activity->approver_ref_id])->first();

            default:
                return null;
        }
    }
}
