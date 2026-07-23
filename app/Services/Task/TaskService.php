<?php

namespace App\Services\Task;

use App\Enum\Task\TaskSettingEnum;
use App\Enum\Task\TaskStatus;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Setting;
use App\Models\Task\Task;
use App\Models\Task\TaskChecklistItem;
use App\Models\Task\TaskProject;
use App\Models\Task\TaskProjectMember;
use App\Models\User;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Repositories\Master\DepartmentRepository;
use App\Repositories\Task\TaskRepository;
use App\Repositories\Task\TaskServiceRepository;
use App\Repositories\User\UserRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\OrgHierarchyService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(
        protected TaskServiceRepository $taskServiceRepository,
        protected TaskRepository $taskRepository,
        protected DepartmentRepository $departmentRepository,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected UserRepository $userRepository,
        protected OrgHierarchyService $orgHierarchyService,
    ) {}

    /**
     * Default toggle "butuh approval" buat form portal — company-scoped, fallback global,
     * lihat App\Enum\Task\TaskSettingEnum.
     */
    public function requiresApprovalByDefault(?string $companyId): bool
    {
        return (bool) Setting::resolve(TaskSettingEnum::REQUIRE_APPROVAL_DEFAULT->value, $companyId, false);
    }

    /**
     * Dipakai portal (bukan lewat Request mobile API) — buat task casual/board tanpa approval,
     * bisa didelegasikan (bukan cuma diri sendiri), boleh isi checklist awal & project_id.
     * $assignedTo selain diri sendiri: kalau ada project_id harus sesama member project itu,
     * kalau tidak ada project harus bawahan pembuat (OrgHierarchyService).
     */
    public function createCasualTask(array $data): Task
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();

            $assignedTo = $data['assigned_to'] ?? $user->id;
            $project = ! empty($data['project_id']) ? TaskProject::findOrFail($data['project_id']) : null;

            if ($project) {
                abort_unless($this->canAccessProject($project, $user), 403, 'Kamu bukan anggota project ini.');
            }

            if ($assignedTo !== $user->id) {
                $isAllowed = $project
                    ? $this->canAccessProject($project, User::find($assignedTo))
                    : $this->orgHierarchyService->subordinates($user)->where('id', $assignedTo)->exists();

                abort_unless($isAllowed, 403, $project ? 'Orang itu bukan anggota project ini.' : 'Hanya bisa menugaskan ke bawahan sendiri.');
            }

            $departmentId = $user->profile?->department?->id;

            $task = $this->taskRepository->create([
                'code' => $this->generateCode($departmentId),
                'department_id' => $departmentId,
                'project_id' => $project?->id,
                'company_id' => $user->company_id,
                'requirement' => $data['requirement'],
                'priority' => $data['priority'] ?? 'medium',
                'due_date' => $data['due_date'] ?? null,
                'created_by' => $user->id,
                'assigned_to' => $assignedTo,
                'is_self_assigned' => $assignedTo === $user->id,
                'responded_date' => Carbon::now(),
                'status' => TaskStatus::TODO->value,
            ]);

            foreach ($data['checklist'] ?? [] as $title) {
                if (trim((string) $title) === '') {
                    continue;
                }

                $task->checklistItems()->create(['title' => $title]);
            }

            return $task;
        });
    }

    /**
     * Bikin project + jadikan pembuat sebagai owner. $memberIds (opsional) langsung ditambah
     * sebagai member biasa.
     */
    public function createProject(array $data): TaskProject
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();

            $project = TaskProject::create([
                'company_id' => $user->company_id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'color' => $data['color'] ?? '#042c6c',
                'owner_id' => $user->id,
            ]);

            TaskProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'role' => TaskProjectMember::ROLE_OWNER,
            ]);

            foreach (array_unique($data['member_ids'] ?? []) as $memberId) {
                if ($memberId === $user->id) {
                    continue;
                }

                TaskProjectMember::create([
                    'project_id' => $project->id,
                    'user_id' => $memberId,
                    'role' => TaskProjectMember::ROLE_MEMBER,
                ]);
            }

            return $project;
        });
    }

    public function canAccessProject(TaskProject $project, User $user): bool
    {
        return $project->members()->where('user_id', $user->id)->exists();
    }

    public function canManageProjectMembers(TaskProject $project, User $user): bool
    {
        return $project->members()->where('user_id', $user->id)->where('role', TaskProjectMember::ROLE_OWNER)->exists();
    }

    public function addProjectMember(TaskProject $project, string $userId, User $actor): void
    {
        abort_unless($this->canManageProjectMembers($project, $actor), 403);

        TaskProjectMember::firstOrCreate([
            'project_id' => $project->id,
            'user_id' => $userId,
        ], ['role' => TaskProjectMember::ROLE_MEMBER]);
    }

    public function removeProjectMember(TaskProject $project, string $userId, User $actor): void
    {
        abort_unless($this->canManageProjectMembers($project, $actor), 403);
        abort_if($userId === $project->owner_id, 422, 'Owner project tidak bisa dikeluarkan.');

        $project->members()->where('user_id', $userId)->delete();
    }

    public function getMyProjects(User $user): Collection
    {
        return TaskProject::whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('name')
            ->get();
    }

    public function getProjectTasks(TaskProject $project): Collection
    {
        return $project->tasks()
            ->with(['assignedTo', 'createdBy', 'checklistItems'])
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Dipakai portal (web guard) — versi createTask() yang butuh approval, tanpa bergantung
     * ke guard 'sanctum' (itu punya createTask() mobile API, cuma resolve lewat web session).
     */
    public function createFormalTask(array $data): Task
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();

            $masterFlow = $this->approvalSnapshotRepository->findMasterApprovalFlowByCompany(
                moduleType: 'task_request',
                companyId: $user->company_id
            );

            abort_if(! $masterFlow, 422, 'Belum ada alur approval tugas untuk company ini — hubungi admin, atau matikan toggle "Butuh Approval".');

            $task = $this->taskRepository->create([
                'code' => $this->generateCode($data['department_id']),
                'service_id' => $data['service_id'],
                'department_id' => $data['department_id'],
                'company_id' => $user->company_id,
                'requirement' => $data['requirement'],
                'priority' => $data['priority'] ?? 'medium',
                'due_date' => $data['due_date'] ?? null,
                'created_by' => $user->id,
                'is_self_assigned' => false,
                'status' => TaskStatus::PENDING->value,
                'sla_reviewer_id' => $user->id,
            ]);

            foreach ($data['checklist'] ?? [] as $title) {
                if (trim((string) $title) === '') {
                    continue;
                }

                $task->checklistItems()->create(['title' => $title]);
            }

            $this->approvalCreationService->createSnapshot(
                moduleType: 'task_request',
                moduleId: $task->id,
                flow: $masterFlow,
                createdBy: $user->id,
                companyId: $user->company_id,
                userId: $user->id
            );

            return $task;
        });
    }

    public function getAllTaskServices(): Collection
    {
        return $this->taskServiceRepository->getAllTaskServices();
    }

    public function getOtherDepartments(): Collection
    {
        $user = Auth::user();
        return $this->departmentRepository->getOtherDepartmentsByUserId($user->id);
    }

    public function getReviewers(): Collection
    {
        $user = Auth::user();
        return $this->userRepository->getReviewers($user);
    }

    public function getTasks(?string $code = null, ?bool $isHighRisk = false): Collection
    {
        $user = Auth::user();
        return $this->taskRepository->getTasksByUser($user->id, $code, $isHighRisk);
    }

    public function getTaskDetail(string $taskId): Task
    {
        $user = Auth::user();
        return $this->taskRepository->getTaskDetailByUser($taskId, $user->id);
    }

    public function createTask(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $user = Auth::user();

            $masterFlow = $this->approvalSnapshotRepository->findMasterApprovalFlowByCompany(
                moduleType: 'task_request',
                companyId: auth('sanctum')->user()->company_id
            );

            if (!$masterFlow) {
                throw new \Exception('No active approval flow found for task requests for this company.');
            }

            $task = $this->taskRepository->create([
                'code' => $this->generateCode($request->department_id),
                'service_id' => $request->service_id,
                'department_id' => $request->department_id,
                'company_id' => $user->company_id,
                'requirement' => $request->requirement,
                'created_by' => $user->id,
                'is_self_assigned' => false,
                'status' => TaskStatus::PENDING->value,
                'sla_reviewer_id' => $user->id
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('tasks/attachments', 'public');
                    $this->taskRepository->addAttachment($task->id, $path);
                }
            }

            // Create approval snapshot
            $this->approvalCreationService->createSnapshot(
                moduleType: 'task_request',
                moduleId: $task->id,
                flow: $masterFlow,
                createdBy: $user->id,
                companyId: auth('sanctum')->user()->company_id,
                userId: $user->id
            );

            return $task->load('attachments');
        });
    }

    public function createSelfTask(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $user = Auth::user();

            $task = $this->taskRepository->create([
                'code' => $this->generateCode($user->profile->department->id),
                'service_id' => $request->service_id,
                'department_id' => $user->profile->department->id,
                'company_id' => $user->company_id,
                'requirement' => $request->requirement,
                'due_date' => $request->due_date,
                'total_cost' => $request->total_cost,
                'unit_cost' => $request->unit_cost,
                'sla_reviewer_id' => $request->sla_reviewer_id,
                'created_by' => auth()->id(),
                'assigned_to' => auth()->id(),
                'is_self_assigned' => true,
                'responded_date' => Carbon::now(),
                'status' => TaskStatus::IN_PROGRESS->value,
            ]);

            return $task->load('attachments');
        });
    }

    private function generateCode(?string $departmentId): string
    {
        $department = $departmentId ? $this->departmentRepository->getDepartmentById($departmentId) : null;
        $prefix = $department
            ? strtoupper(substr(preg_replace('/\s+/', '', $department->name), 0, 3))
            : 'TSK';

        $lastTask = $this->taskRepository->getLastTaskByDepartment($department?->id);

        $lastNumber = 0;
        if ($lastTask && preg_match('/' . $prefix . '(\d{5})/', $lastTask->code, $matches)) {
            $lastNumber = (int) $matches[1];
        }
        $nextNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        return $prefix . $nextNumber;
    }

    public function updateTask(string $taskId, $request)
    {
        return DB::transaction(function () use ($taskId, $request) {
            $user = Auth::user();
            $task = $this->taskRepository->getTaskDetailByUser($taskId, $user->id);

            $updateData = $request->only([
                'completed_date',
                'total_cost',
                'unit_cost',
                'risk_level',
                'collaboration_note',
                'root_cause',
                'solution',
            ]);

            $updateData['is_overdue'] = $task->due_date && $request->completed_at > $task->due_date;

            $this->taskRepository->update($taskId, $updateData);

            if ($request->filled('proofs_to_delete')) {
                $this->taskRepository->deleteProofs($request->proofs_to_delete);
            }

            if ($request->hasFile('proofs')) {
                foreach ($request->file('proofs') as $file) {
                    $path = $file->store('tasks/proofs', 'public');
                    $this->taskRepository->addProof($taskId, $path);
                }
            }

            return $this->taskRepository->getTaskDetailByUser($taskId, $user->id);
        });
    }

    public function reviewTask(string $taskId, $request)
    {
        return DB::transaction(function () use ($taskId, $request) {
            $user = Auth::user();

            $task = $this->taskRepository->getTaskDetailByUser($taskId, $user->id);

            if ($task->sla_reviewer_id !== $user->id) {
                throw new \Exception('You are not authorized to review this task.');
            }

            if ($task->review) {
                throw new \Exception('This task has already been reviewed.');
            }

            $reviewData = [
                'task_id' => $taskId,
                'reviewer_id' => $user->id,
                'rating' => $request->rating,
                'comment' => $request->comment,
            ];

            $this->taskRepository->addReview($taskId, $reviewData);

            $updateData = [
                'status' => TaskStatus::COMPLETE->value,
            ];
            $this->taskRepository->update($taskId, $updateData);

            return $this->taskRepository->getTaskDetailByUser($taskId, $user->id);
        });
    }

    /**
     * Task yang tampil di halaman Tugas portal — gabungan task milik user (assigned_to atau
     * created_by) dan, kalau dia punya bawahan, task yang assigned_to bawahannya (reuse
     * OrgHierarchyService, pengganti board membership Kanban lama).
     */
    public function getPortalTasks(User $user, string $scope = 'saya'): Collection
    {
        return Task::query()
            ->where('company_id', $user->company_id)
            ->when($scope === 'saya', fn ($q) => $q->where('assigned_to', $user->id))
            ->when($scope === 'dibuat', fn ($q) => $q->where('created_by', $user->id))
            ->when($scope === 'tim', function ($q) use ($user) {
                $subordinateIds = $this->orgHierarchyService->subordinates($user)->pluck('id');
                $q->whereIn('assigned_to', $subordinateIds);
            })
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Pindah status task di board. Aturan: cuma assigned_to/created_by yang boleh, cuma maju
     * 1 langkah berurutan di TaskStatus::BOARD_ORDER, boleh mundur 1 langkah dari REVIEW ke
     * IN_PROGRESS, COMPLETE terkunci, PENDING tidak movable (nunggu approve).
     */
    public function moveStatus(Task $task, string $newStatus, User $user): void
    {
        abort_unless(
            $task->assigned_to === $user->id || $task->created_by === $user->id,
            403
        );

        abort_if($task->status === TaskStatus::PENDING->value, 422, 'Tugas masih menunggu approval.');
        abort_if($task->status === TaskStatus::COMPLETE->value, 422, 'Tugas yang sudah selesai tidak bisa dipindah lagi.');

        $order = TaskStatus::BOARD_ORDER;
        $currentIndex = array_search(TaskStatus::from($task->status), $order, true);
        $nextStatus = $order[$currentIndex + 1] ?? null;

        $isNextStep = $nextStatus?->value === $newStatus;
        $isPullBack = $task->status === TaskStatus::REVIEW->value && $newStatus === TaskStatus::IN_PROGRESS->value;

        abort_unless($isNextStep || $isPullBack, 422, 'Tugas hanya bisa dipindah 1 langkah berurutan.');

        $updateData = ['status' => $newStatus];

        if ($newStatus === TaskStatus::COMPLETE->value) {
            $updateData['completed_date'] = Carbon::now();
            $updateData['is_overdue'] = $task->due_date && Carbon::now()->gt($task->due_date);
        }

        $this->taskRepository->update($task->id, $updateData);
    }

    public function toggleChecklistItem(TaskChecklistItem $item): void
    {
        $item->update(['is_done' => ! $item->is_done]);
    }

    public function addComment(Task $task, User $user, string $comment): void
    {
        $task->comments()->create([
            'user_id' => $user->id,
            'comment' => $comment,
        ]);
    }
}
