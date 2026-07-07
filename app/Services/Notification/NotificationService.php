<?php

namespace App\Services\Notification;

use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationService
{
    public function sendOvertimeRequestNotification(
        User $approver,
        array $requestData,
        string $requesterName
    ): void {
        $notificationId = Str::uuid();
        
        DatabaseNotification::create([
            'id' => $notificationId,
            'type' => 'App\\Notifications\\OvertimeRequestSubmitted',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $approver->id,
            'data' => [
                'title' => 'Pengajuan Lembur Baru',
                'body' => "Pengajuan lembur dari {$requesterName} memerlukan persetujuan Anda",
                'request_type' => 'overtime',
                'request_id' => $requestData['request_id'],
                'requester_name' => $requesterName,
                'overtime_type' => $requestData['overtime_type'],
                'date' => $requestData['date'],
                'start_time' => $requestData['start_time'],
                'end_time' => $requestData['end_time'],
                'created_at' => now()->toISOString(),
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function sendLeaveRequestNotification(
        User $approver,
        array $requestData,
        string $requesterName
    ): void {
        $notificationId = Str::uuid();
        
        DatabaseNotification::create([
            'id' => $notificationId,
            'type' => 'App\\Notifications\\LeaveRequestSubmitted',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $approver->id,
            'data' => [
                'title' => 'Pengajuan Cuti Baru',
                'body' => "Pengajuan cuti dari {$requesterName} memerlukan persetujuan Anda",
                'request_type' => 'leave',
                'request_id' => $requestData['request_id'],
                'requester_name' => $requesterName,
                'leave_type' => $requestData['leave_type'],
                'start_date' => $requestData['start_date'],
                'end_date' => $requestData['end_date'],
                'created_at' => now()->toISOString(),
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function sendPermissionRequestNotification(
        User $approver,
        array $requestData,
        string $requesterName
    ): void {
        $notificationId = Str::uuid();
        
        DatabaseNotification::create([
            'id' => $notificationId,
            'type' => 'App\\Notifications\\PermissionRequestSubmitted',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $approver->id,
            'data' => [
                'title' => 'Pengajuan Izin Baru',
                'body' => "Pengajuan izin dari {$requesterName} memerlukan persetujuan Anda",
                'request_type' => 'permission',
                'request_id' => $requestData['request_id'],
                'requester_name' => $requesterName,
                'permission_type' => $requestData['permission_type'],
                'date' => $requestData['date'],
                'start_time' => $requestData['start_time'],
                'end_time' => $requestData['end_time'],
                'purpose' => $requestData['purpose'],
                'created_at' => now()->toISOString(),
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
                    $departmentId = $activity->scope_ref_id;
                    $query->whereHas('profile.orgStructure', function ($q) use ($departmentId) {
                        $q->where('id', $departmentId);
                    });
                }
                
                return $query->first();
                
            case 'job_position':
                return User::whereHas('profile.jobPosition', function ($q) use ($activity) {
                    $q->where('id', $activity->approver_ref_id);
                })->first();
                
            default:
                return null;
        }
    }
}