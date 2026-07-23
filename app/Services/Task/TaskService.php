<?php

namespace App\Services\Task;

use App\Enum\Task\TaskSettingEnum;
use App\Enum\Task\TaskStatus;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Setting;
use App\Models\Task\Task;
use App\Models\Task\TaskAttachment;
use App\Models\Task\TaskChecklistItem;
use App\Models\Task\TaskProject;
use App\Models\Task\TaskProjectMember;
use App\Models\Task\TaskReview;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use App\Notifications\TaskReviewDecided;
use App\Notifications\TaskReviewerAssigned;
use App\Notifications\TaskProjectMemberAdded;
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
use Illuminate\Http\UploadedFile;
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
                if ($project) {
                    abort_unless($this->canAssignInProject($project, $user), 403, 'Kamu tidak punya izin menugaskan ke anggota lain di project ini — cuma owner atau anggota yang ditandai "Assigner" yang boleh.');
                    abort_unless($this->canAccessProject($project, User::find($assignedTo)), 403, 'Orang itu bukan anggota project ini.');
                } else {
                    abort_unless($this->orgHierarchyService->subordinates($user)->where('id', $assignedTo)->exists(), 403, 'Hanya bisa menugaskan ke bawahan sendiri.');
                }
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

            $this->assignReviewers($task, $data['reviewer_ids'] ?? []);

            if ($assignedTo !== $user->id) {
                User::find($assignedTo)?->notify(new TaskAssigned($task, $user->name));
            }

            return $task;
        });
    }

    /** Reviewer portal dipilih manual saat bikin task (bukan auto dari created_by) — and-sign,
     * lihat TaskService::approveReview()/rejectReview(). Dipanggil dari createCasualTask() &
     * createFormalTask(), bukan dari createTask()/createSelfTask() (jalur mobile, tetap pakai
     * sla_reviewer_id tunggal). */
    private function assignReviewers(Task $task, array $reviewerIds): void
    {
        foreach (array_unique($reviewerIds) as $reviewerId) {
            $task->reviews()->create([
                'reviewer_id' => $reviewerId,
                'status' => TaskReview::STATUS_PENDING,
            ]);

            User::find($reviewerId)?->notify(new TaskReviewerAssigned($task, $task->createdBy?->name ?? '-'));
        }
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

                User::find($memberId)?->notify(new TaskProjectMemberAdded($project, $user->name));
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

    /** Owner selalu boleh assign; member biasa cuma kalau ditandai is_assigner oleh owner. */
    public function canAssignInProject(TaskProject $project, User $user): bool
    {
        $member = $project->members()->where('user_id', $user->id)->first();

        return $member?->canAssign() ?? false;
    }

    public function addProjectMember(TaskProject $project, string $userId, User $actor): void
    {
        abort_unless($this->canManageProjectMembers($project, $actor), 403);

        $member = TaskProjectMember::firstOrCreate([
            'project_id' => $project->id,
            'user_id' => $userId,
        ], ['role' => TaskProjectMember::ROLE_MEMBER]);

        if ($member->wasRecentlyCreated) {
            User::find($userId)?->notify(new TaskProjectMemberAdded($project, $actor->name));
        }
    }

    /** Cuma owner yang boleh menunjuk/cabut status "Assigner" member lain. */
    public function setProjectMemberAssigner(TaskProject $project, string $userId, bool $isAssigner, User $actor): void
    {
        abort_unless($this->canManageProjectMembers($project, $actor), 403);

        $project->members()->where('user_id', $userId)->update(['is_assigner' => $isAssigner]);
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
            ->with(['assignedTo', 'createdBy', 'checklistItems', 'comments.user'])
            ->withCount(['attachments', 'reviews'])
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
            ]);

            foreach ($data['checklist'] ?? [] as $title) {
                if (trim((string) $title) === '') {
                    continue;
                }

                $task->checklistItems()->create(['title' => $title]);
            }

            $this->assignReviewers($task, $data['reviewer_ids'] ?? []);

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
                    $this->taskRepository->addAttachment($task->id, [
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'uploaded_by' => $user->id,
                    ]);
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
     * And-sign: reviewer approve baris miliknya, task Complete cuma kalau SEMUA baris
     * task_reviews sudah approved. Khusus portal — jalur mobile tetap pakai reviewTask().
     */
    public function approveReview(Task $task, User $user, int $rating, ?string $comment): void
    {
        abort_unless($task->status === TaskStatus::REVIEW->value, 422, 'Tugas ini tidak sedang ditinjau.');

        $row = $task->reviews()->where('reviewer_id', $user->id)->where('status', TaskReview::STATUS_PENDING)->first();
        abort_unless($row, 403, 'Kamu tidak punya review yang menunggu untuk tugas ini.');

        $row->update([
            'status' => TaskReview::STATUS_APPROVED,
            'rating' => $rating,
            'comment' => $comment,
            'decided_at' => now(),
        ]);

        $allApproved = ! $task->reviews()->where('status', '!=', TaskReview::STATUS_APPROVED)->exists();

        if ($allApproved) {
            $task->update([
                'status' => TaskStatus::COMPLETE->value,
                'completed_date' => now(),
                'is_overdue' => $task->due_date && now()->gt($task->due_date),
            ]);
        }

        if ($task->assigned_to && $task->assigned_to !== $user->id) {
            User::find($task->assigned_to)?->notify(new TaskReviewDecided($task, $user->name, true));
        }
    }

    /**
     * Kebalikan approveReview() — reviewer manapun (atau pembuat task, walau bukan reviewer
     * terdaftar) tolak hasil kerja & kembalikan task ke IN_PROGRESS, bukan status REJECTED
     * (itu terminal, dipakai jalur approval formal — beda konsep). Reject bersifat ringan:
     * baris reviewer LAIN yang sudah approved TIDAK direset — mereka tidak perlu vote ulang,
     * cuma baris yang reject ini yang akan direset ke pending saat task disubmit lagi ke
     * REVIEW (lihat moveStatus()). Catatan ("kenapa ditolak") tetap disimpan di kolom
     * `rejected_reason` di Task (dari sesi sebelumnya) — cuma ditampilkan ke assigned_to.
     */
    public function rejectReview(Task $task, User $user, string $note): void
    {
        abort_unless($task->status === TaskStatus::REVIEW->value, 422, 'Tugas ini tidak sedang ditinjau.');

        $row = $task->reviews()->where('reviewer_id', $user->id)->first();
        abort_unless($row || $task->created_by === $user->id, 403, 'Kamu tidak punya izin menolak tinjauan tugas ini.');

        $row?->update([
            'status' => TaskReview::STATUS_REJECTED,
            'rejected_note' => $note,
            'decided_at' => now(),
        ]);

        $task->update([
            'status' => TaskStatus::IN_PROGRESS->value,
            'rejected_reason' => $note,
            'rejection_note_by' => $user->id,
            'rejection_note_at' => now(),
        ]);

        if ($task->assigned_to && $task->assigned_to !== $user->id) {
            User::find($task->assigned_to)?->notify(new TaskReviewDecided($task, $user->name, false));
        }
    }

    /**
     * Task yang tampil di halaman Tugas portal — gabungan task milik user (assigned_to atau
     * created_by) dan, kalau dia punya bawahan, task yang assigned_to bawahannya (reuse
     * OrgHierarchyService, pengganti board membership Kanban lama).
     */
    /**
     * $riwayatStatus cuma dipakai kalau $scope === 'riwayat' — beda dari saya/dibuat/tim
     * (langsung 1 tampilan gabungan), Riwayat di sidebar itu section sendiri (sejajar Project)
     * isinya 1 tombol per status (Selesai/Ditolak), jadi query-nya perlu tahu status MANA yang
     * lagi dipilih, bukan gabungan semua status riwayat sekaligus.
     */
    public function getPortalTasks(User $user, string $scope = 'saya', ?string $riwayatStatus = null): Collection
    {
        return Task::query()
            ->where('company_id', $user->company_id)
            ->when($scope === 'saya', fn ($q) => $q->where('assigned_to', $user->id))
            ->when($scope === 'dibuat', fn ($q) => $q->where('created_by', $user->id))
            ->when($scope === 'tim', function ($q) use ($user) {
                $subordinateIds = $this->orgHierarchyService->subordinates($user)->pluck('id');
                $q->whereIn('assigned_to', $subordinateIds);
            })
            ->when($scope === 'riwayat', function ($q) use ($user, $riwayatStatus) {
                $subordinateIds = $this->orgHierarchyService->subordinates($user)->pluck('id');
                $status = in_array($riwayatStatus, [TaskStatus::COMPLETE->value, TaskStatus::REJECTED->value], true)
                    ? $riwayatStatus
                    : TaskStatus::COMPLETE->value;

                $q->where('status', $status)
                    ->where(function ($q2) use ($user, $subordinateIds) {
                        $q2->where('assigned_to', $user->id)
                            ->orWhere('created_by', $user->id)
                            ->orWhereIn('assigned_to', $subordinateIds);
                    });
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
        abort_if($newStatus === TaskStatus::COMPLETE->value && $task->reviews()->exists(), 422, 'Tugas ini butuh review dulu.');

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

        if ($newStatus === TaskStatus::REVIEW->value) {
            // Submit/resubmit ke tinjauan — reviewer yang tadi reject boleh vote ulang,
            // yang sudah approved dari ronde sebelumnya TIDAK direset (reject ringan, lihat
            // rejectReview()).
            $task->reviews()->where('status', TaskReview::STATUS_REJECTED)->update([
                'status' => TaskReview::STATUS_PENDING,
                'rejected_note' => null,
                'decided_at' => null,
            ]);
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

        $recipientIds = collect([$task->assigned_to, $task->created_by])
            ->filter()
            ->unique()
            ->reject(fn ($id) => $id === $user->id);

        foreach ($recipientIds as $recipientId) {
            User::find($recipientId)?->notify(new TaskCommented($task, $user->name, $comment));
        }
    }

    public function addFileAttachment(Task $task, User $user, UploadedFile $file): void
    {
        $path = $file->store('tasks/attachments', 'public');

        $this->taskRepository->addAttachment($task->id, [
            'type' => TaskAttachment::TYPE_FILE,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'uploaded_by' => $user->id,
        ]);
    }

    public function addLinkAttachment(Task $task, User $user, string $url, ?string $label): void
    {
        $this->taskRepository->addAttachment($task->id, [
            'type' => TaskAttachment::TYPE_LINK,
            'url' => $url,
            'file_name' => $label ?: $url,
            'uploaded_by' => $user->id,
        ]);
    }

    public function removeAttachment(string $attachmentId): void
    {
        $this->taskRepository->deleteAttachment($attachmentId);
    }
}
