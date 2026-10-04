<?php

namespace App\Services\Approval;

use App\Models\ApprovalManagement\ApprovalActivities;
use App\Models\User;
use App\Repositories\Approval\ApprovalActivityRepository;
use App\Services\Identity\EmployeeContext;
use Illuminate\Support\Collection;

class ApprovalPermissionService
{
     public function __construct(
        protected ApprovalActivityRepository $activityRepo,
    ) {}

    public function canApprove(string $snapshotId, User $user): bool
    {
        $activity = $this->activityRepo->findBySnapshot($snapshotId);

        if (!$activity || $activity->status !== 'pending') {
            return false;
        }

        return $this->checkUserPermission($activity, $user);
    }

    public function hasPermission(string $snapshotId, User $user): bool
    {
        $activity = $this->activityRepo->findBySnapshot($snapshotId);

        if (!$activity ) {
            return false;
        }

        return $this->checkUserPermission($activity, $user);
    }

    /**
     * Semua approval activity yang masih pending dan user ini berwenang approve,
     * dibatasi ke 1 module type (mis. 'attendance_request').
     *
     * @return Collection<int, ApprovalActivities>
     */
    public function pendingApprovalsFor(User $user, string $moduleType): Collection
    {
        $roleIds = $user->roles()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $jobPositionId = EmployeeContext::jobPositionId($user->id);

        $candidates = ApprovalActivities::query()
            ->where('status', 'pending')
            ->whereHas('snapshot', fn ($q) => $q->where('module_type', $moduleType))
            ->where(function ($q) use ($user, $roleIds, $jobPositionId) {
                $q->where(fn ($q2) => $q2->where('approver_type', 'user')->where('approver_ref_id', $user->id));

                if (! empty($roleIds)) {
                    $q->orWhere(fn ($q2) => $q2->where('approver_type', 'role')->whereIn('approver_ref_id', $roleIds));
                }

                if ($jobPositionId) {
                    $q->orWhere(fn ($q2) => $q2->where('approver_type', 'job_position')->where('approver_ref_id', $jobPositionId));
                }
            })
            ->with('snapshot')
            ->get();

        // Saring ulang pakai logic yang sama dengan cek 1-record (scope department, dst) — jangan duplikasi aturan.
        return $candidates->filter(fn ($activity) => $this->checkUserPermission($activity, $user))->values();
    }

    protected function checkUserPermission($activity, User $user): bool
    {
        switch ($activity->approver_type) {
            case 'user':
                return $activity->approver_ref_id === $user->id;

            case 'role':
                return $this->checkRolePermission($activity, $user);

            case 'job_position':
                return EmployeeContext::jobPositionId($user->id) === $activity->approver_ref_id;

            default:
                return false;
        }
    }

    private function checkRolePermission($activity, User $user): bool
    {
        $hasRole = $user->hasRole(intval($activity->approver_ref_id));

        if (!$hasRole) {
            return false;
        }

        if ($activity->scope_type === 'specific_department') {
            return EmployeeContext::departmentId($user->id) === $activity->scope_ref_id;
        }

        return true;
    }

    public function approvalDeniedReason(string $snapshotId, User $user): ?string
    {
        $activity = $this->activityRepo->findBySnapshot($snapshotId);
        
        if (!$activity) {
            return 'Approval activity tidak ditemukan.';
        }

        if ($activity->status !== 'pending') {
            return "Approval saat ini sudah {$activity->status}.";
        }

        return $this->getDeniedReason($activity, $user);
    }

    private function getDeniedReason($activity, User $user): ?string
    {
        switch ($activity->approver_type) {
            case 'user':
                return $activity->approver_ref_id !== $user->id 
                    ? 'User ini bukan approver langsung.' 
                    : null;

            case 'role':
                if (!$user->hasRole($activity->approver_ref_id)) {
                    return 'User ini tidak memiliki role yang sesuai.';
                }
                
                if ($activity->scope_type === 'specific_department' && 
                    EmployeeContext::orgStructureId($user->id) !== $activity->scope_ref_id) {
                    return 'User ini tidak berada di department yang sesuai.';
                }
                
                return null;

            case 'job_position':
                return EmployeeContext::jobPositionId($user->id) !== $activity->approver_ref_id
                    ? 'User ini tidak sesuai dengan jabatan yang ditentukan.' 
                    : null;

            default:
                return 'Tipe approver tidak dikenali.';
        }
    }
}
