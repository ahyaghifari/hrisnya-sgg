<?php

namespace App\Services\Task;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\Task\TaskSettingEnum;
use App\Enum\Task\TaskStatus;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Setting;
use App\Models\Task\Task;
use App\Models\Task\TaskAttachment;
use App\Models\Task\TaskBoardBackground;
use App\Models\Task\TaskChecklistItem;
use App\Models\Task\TaskList;
use App\Models\Task\TaskProject;
use App\Models\Task\TaskProjectMember;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use App\Notifications\TaskCompletedByAssignee;
use App\Notifications\TaskProjectMemberAdded;
use App\Notifications\TaskReviewDecided;
use App\Notifications\TaskReviewRequested;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Repositories\Master\DepartmentRepository;
use App\Repositories\Task\TaskRepository;
use App\Repositories\Task\TaskServiceRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\OrgHierarchyService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TaskService
{
    public function __construct(
        protected TaskServiceRepository $taskServiceRepository,
        protected TaskRepository $taskRepository,
        protected DepartmentRepository $departmentRepository,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
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

            // Task di dalam Board masuk List pertama (position terkecil) kalau tidak dipilih
            // eksplisit - Board tidak pakai step-order REVIEW sama sekali, jadi needs_review
            // dipaksa false di sini juga (bukan cuma disembunyikan di UI).
            $listId = $data['list_id'] ?? null;
            if ($project && ! $listId) {
                $listId = $project->lists()->orderBy('position')->value('id');
            }

            $task = $this->taskRepository->create([
                'code' => $this->generateCode($departmentId),
                'department_id' => $departmentId,
                'project_id' => $project?->id,
                'list_id' => $project ? $listId : null,
                'company_id' => $user->company_id,
                'requirement' => $data['requirement'],
                'priority' => $data['priority'] ?? 'medium',
                'due_date' => $data['due_date'] ?? null,
                'created_by' => $user->id,
                'assigned_to' => $assignedTo,
                'is_self_assigned' => $assignedTo === $user->id,
                'needs_review' => $project ? false : (bool) ($data['needs_review'] ?? false),
                'notify_creator_on_review' => $project ? false : (bool) ($data['notify_creator_on_review'] ?? false),
                'notify_creator_on_complete' => (bool) ($data['notify_creator_on_complete'] ?? false),
                'responded_date' => Carbon::now(),
                'status' => TaskStatus::TODO->value,
            ]);

            foreach ($data['checklist'] ?? [] as $title) {
                if (trim((string) $title) === '') {
                    continue;
                }

                $task->checklistItems()->create(['title' => $title, 'created_by' => $user->id]);
            }

            if ($assignedTo !== $user->id) {
                User::find($assignedTo)?->notify(new TaskAssigned($task, $user->name));
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

                User::find($memberId)?->notify(new TaskProjectMemberAdded($project, $user->name));
            }

            // Board baru langsung siap pakai - List terakhir ditandai "Selesai" (is_final), owner
            // masih bebas ubah/hapus/tambah List sesudahnya.
            foreach (['To Do', 'Sedang Dikerjakan', 'Selesai'] as $i => $name) {
                $project->lists()->create([
                    'name' => $name,
                    'position' => $i,
                    'is_final' => $name === 'Selesai',
                ]);
            }

            return $project;
        });
    }

    /** Hapus/nonaktifkan Board - owner-only. Soft-delete (TaskProject pakai SoftDeletes) - board
     * langsung hilang dari daftar Board siapa pun (getMyProjects() otomatis exclude yang
     * trashed), task di dalamnya tetap ada di database (bukan ikut terhapus), cuma jadi tidak
     * bisa diakses lagi karena board-nya sendiri sudah tidak muncul di mana pun. */
    public function deleteProject(TaskProject $project, User $actor): void
    {
        abort_unless($this->canManageProjectMembers($project, $actor), 403);

        $project->delete();
    }

    /** Bikin List baru di Board - owner-only. */
    public function createList(TaskProject $project, string $name, User $actor, ?string $color = null): TaskList
    {
        abort_unless($this->canManageProjectMembers($project, $actor), 403);

        $position = ((int) $project->lists()->max('position')) + 1;

        return $project->lists()->create(['name' => $name, 'color' => $color, 'position' => $position]);
    }

    /** Ubah warna header List - owner-only. */
    public function updateListColor(TaskList $list, string $color, User $actor): void
    {
        abort_unless($this->canManageProjectMembers($list->project, $actor), 403);

        $list->update(['color' => $color]);
    }

    /** Ubah nama List - owner-only. */
    public function renameList(TaskList $list, string $name, User $actor): void
    {
        abort_unless($this->canManageProjectMembers($list->project, $actor), 403);

        $list->update(['name' => $name]);
    }

    /** Hapus List - owner-only, cuma boleh kalau List-nya sudah kosong. */
    public function deleteList(TaskList $list, User $actor): void
    {
        abort_unless($this->canManageProjectMembers($list->project, $actor), 403);
        abort_if($list->tasks()->exists(), 422, 'Pindahkan task keluar dari List ini dulu sebelum menghapusnya.');

        $list->delete();
    }

    /** Reorder List (kolom) di 1 Board - owner-only, $orderedListIds = urutan akhir yang diinginkan. */
    public function reorderLists(TaskProject $project, array $orderedListIds, User $actor): void
    {
        abort_unless($this->canManageProjectMembers($project, $actor), 403);

        foreach (array_values($orderedListIds) as $index => $listId) {
            TaskList::where('id', $listId)->where('project_id', $project->id)->update(['position' => $index]);
        }
    }

    /** Tandai 1 List sebagai "Selesai" (max 1 per Board) - owner-only. */
    public function setListFinal(TaskList $list, User $actor): void
    {
        abort_unless($this->canManageProjectMembers($list->project, $actor), 403);

        DB::transaction(function () use ($list) {
            TaskList::where('project_id', $list->project_id)->update(['is_final' => false]);
            $list->update(['is_final' => true]);
        });
    }

    /**
     * Pindah/reorder card antar List di 1 Board - semua anggota Board boleh (beda dari kelola
     * struktur List yang owner-only), konsisten kayak drag-card di Trello asli.
     * $listsPayload: [list_id => [task_id, task_id, ...], ...] - 1 entry kalau cuma reorder dalam
     * 1 List, 2 entry kalau task pindah List (target + sisa List asal).
     *
     * List yang jadi `is_final` diperlakukan setara status COMPLETE (reuse alur rating/notifikasi
     * lama yang gate di $task->status, lihat TaskService::moveStatus()/rateTask()) - card yang
     * masuk situ dapat status=complete, keluar lagi balik ke in_progress. Tidak ada batas 24 jam
     * kayak moveStatus() - Board lebih casual, siapa saja anggota boleh reopen kapan saja.
     */
    public function moveCardsAcrossLists(array $listsPayload, User $actor): void
    {
        if (empty($listsPayload)) {
            return;
        }

        $lists = TaskList::whereIn('id', array_keys($listsPayload))->get()->keyBy('id');
        abort_unless($lists->count() === count($listsPayload), 404);

        $projectIds = $lists->pluck('project_id')->unique();
        abort_unless($projectIds->count() === 1, 422, 'List harus berasal dari Board yang sama.');

        $project = TaskProject::findOrFail($projectIds->first());
        abort_unless($this->canAccessProject($project, $actor), 403);

        DB::transaction(function () use ($listsPayload, $lists) {
            foreach ($listsPayload as $listId => $taskIds) {
                $list = $lists->get($listId);

                foreach (array_values($taskIds) as $index => $taskId) {
                    $task = Task::find($taskId);
                    if (! $task) {
                        continue;
                    }

                    $updateData = ['list_id' => $listId, 'position' => $index];

                    if ($task->list_id !== $listId) {
                        if ($list->is_final) {
                            $updateData['status'] = TaskStatus::COMPLETE->value;
                            $updateData['completed_date'] = Carbon::now();
                            $updateData['is_overdue'] = $task->due_date && Carbon::now()->gt($task->due_date);

                            if ($task->notify_creator_on_complete && $task->assigned_to !== $task->created_by) {
                                $task->createdBy?->notify(new TaskCompletedByAssignee($task, $task->assignedTo?->name ?? '-'));
                            }
                        } elseif ($task->list?->is_final) {
                            $updateData['status'] = TaskStatus::IN_PROGRESS->value;
                            $updateData['completed_date'] = null;
                            $updateData['is_overdue'] = false;
                        }
                    }

                    $this->taskRepository->update($task->id, $updateData);
                }
            }
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
            ->with(['assignedTo.profile', 'createdBy', 'checklistItems', 'comments.user', 'list'])
            ->withCount('attachments')
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
                moduleType: ApprovalModuleEnum::TASK_REQUEST->value,
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
                'status' => TaskStatus::TODO->value,
            ]);

            foreach ($data['checklist'] ?? [] as $title) {
                if (trim((string) $title) === '') {
                    continue;
                }

                $task->checklistItems()->create(['title' => $title, 'created_by' => $user->id]);
            }

            $this->approvalCreationService->createSnapshot(
                moduleType: ApprovalModuleEnum::TASK_REQUEST->value,
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

    /**
     * Task yang tampil di halaman Tugas portal — scope (tab horizontal, bukan sidebar lagi,
     * 2026-08-02):
     * - 'todo' = catatan pribadi (is_self_assigned=true, assigned_to = aku SENDIRI yang assign).
     * - 'saya' = yang di-assign ORANG LAIN/atasan ke aku (assigned_to = aku, is_self_assigned=false)
     *   - bukan superset dari 'todo' lagi (dipisah 2026-08-02), murni tugas "kiriman" orang lain.
     * - 'individu' = "Penugasan Individu", tugas yang AKU assign ke ORANG LAIN (created_by = aku,
     *   assigned_to != aku).
     * - 'tim' = "Tim Saya" (2026-08-02) - task yang DIKERJAKAN bawahan (rekursif seluruh hierarki
     *   di bawahku, lewat OrgHierarchyService::subordinates), siapapun yang nugasin - oversight
     *   murni, aku ngga harus jadi creator/assignee-nya.
     */
    private function scopedTaskQuery(User $user, string $scope)
    {
        return Task::query()
            ->where('company_id', $user->company_id)
            // Tab personal (Todo Saya/Tugas Saya/Penugasan Individu/Tim Saya) cuma buat task
            // di luar Board - task di dalam Project (termasuk yang self-assigned, dibuat &
            // dikerjakan orang yang sama) tetap eksklusif di board-nya sendiri, tidak boleh
            // "bocor" ke sini walau kondisi assigned_to/is_self_assigned-nya kebetulan cocok.
            ->whereNull('project_id')
            ->when($scope === 'todo', fn ($q) => $q->where('assigned_to', $user->id)->where('is_self_assigned', true))
            ->when($scope === 'saya', fn ($q) => $q->where('assigned_to', $user->id)->where('is_self_assigned', false))
            ->when($scope === 'individu', fn ($q) => $q->where('created_by', $user->id)->where(function ($q2) use ($user) {
                $q2->whereNull('assigned_to')->orWhere('assigned_to', '!=', $user->id);
            }))
            // 'tim' = task yang DIKERJAKAN bawahan (rekursif, seluruh hierarki di bawah user),
            // siapapun yang nugasin - buat oversight atasan (mis. HC Manager liat kerjaan Supervisor
            // ke staff-nya, staff ke anak magang, dst), bukan cuma langsung ke user.
            ->when($scope === 'tim', fn ($q) => $q->whereIn('assigned_to', $this->orgHierarchyService->subordinates($user)->pluck('id')));
    }

    /**
     * Task yang tampil di board - task COMPLETE yang udah lewat H+1 dari completed_date pindah
     * ke riwayat (getHistoryTasks), ngga nongol di board lagi (2026-08-02).
     */
    public function getPortalTasks(User $user, string $scope = 'saya'): Collection
    {
        return $this->scopedTaskQuery($user, $scope)
            ->where(function ($q) {
                $q->where('status', '!=', TaskStatus::COMPLETE->value)
                    ->orWhere('completed_date', '>', Carbon::now()->subDay());
            })
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();
    }

    /** Riwayat - task COMPLETE yang udah lewat H+1 dari completed_date, dipaginate. */
    public function getHistoryTasks(User $user, string $scope, int $perPage = 10)
    {
        return $this->scopedTaskQuery($user, $scope)
            ->where('status', TaskStatus::COMPLETE->value)
            ->where('completed_date', '<=', Carbon::now()->subDay())
            ->orderByDesc('completed_date')
            ->paginate($perPage);
    }

    /**
     * Edit requirement/due_date/priority. Creator (atau diri sendiri buat Todo Saya) boleh edit
     * selama task belum COMPLETE.
     */
    public function updateTaskDetails(Task $task, array $data, User $user): void
    {
        $isOwner = $task->is_self_assigned ? $task->assigned_to === $user->id : $task->created_by === $user->id;
        abort_unless($isOwner, 403);
        abort_if($task->status === TaskStatus::COMPLETE->value, 422, 'Tugas yang sudah selesai tidak bisa diedit.');

        $this->taskRepository->update($task->id, [
            'requirement' => $data['requirement'],
            'due_date' => $data['due_date'] ?? null,
            'priority' => $data['priority'],
        ]);
    }

    /**
     * Todo Saya (self-assigned) bebas dihapus kapan pun. Penugasan Individu cuma boleh dihapus
     * creator-nya sebelum tahap REVIEW (masih TODO/IN_PROGRESS).
     */
    public function deleteTask(Task $task, User $user): void
    {
        if ($task->is_self_assigned) {
            abort_unless($task->assigned_to === $user->id, 403);
        } else {
            abort_unless($task->created_by === $user->id, 403);
            abort_unless(
                in_array($task->status, [TaskStatus::TODO->value, TaskStatus::IN_PROGRESS->value], true),
                422,
                'Tugas yang sudah masuk review/selesai tidak bisa dihapus.'
            );
        }

        $task->delete();
    }

    /**
     * Pindah status task di board. Aturan: cuma assigned_to/created_by yang boleh, cuma maju
     * 1 langkah berurutan - KECUALI dari REVIEW, boleh mundur ke IN_PROGRESS (creator kirim
     * balik buat direvisi) selain maju ke COMPLETE (creator approve). Task self-assigned
     * ("Todo Saya") atau needs_review=false (pembuat ngga minta ditinjau ulang - "Butuh Approval
     * Saya" ngga dicentang saat bikin) lewatin REVIEW (BOARD_ORDER_SELF_ASSIGNED:
     * TODO -> IN_PROGRESS -> COMPLETE), task lain lewat BOARD_ORDER penuh termasuk REVIEW.
     * Task formal yang masih nunggu approval (approvalSnapshot->status === 'in_review') belum
     * movable - status kerja & status approval dipisah sejak 2026-08-02 (TaskStatus cuma 4 case).
     */
    public function moveStatus(Task $task, string $newStatus, User $user): void
    {
        abort_unless(
            $task->assigned_to === $user->id || $task->created_by === $user->id,
            403
        );

        // Di TODO ("belum dikerjakan") cuma yang ditugaskan yang boleh mulai - creator ngga bisa
        // gesa-gesa mindahin status tugas orang lain sebelum pelaksananya sendiri yang mulai.
        abort_if(
            $task->status === TaskStatus::TODO->value && $task->assigned_to !== $user->id,
            403,
            'Cuma yang ditugaskan yang bisa mengubah status tugas ini.'
        );

        // Di REVIEW cuma creator yang boleh mutusin (kembalikan buat direvisi atau selesaikan) -
        // pelaksana udah nyerahin, giliran pembuat tugas yang menilai.
        abort_if(
            $task->status === TaskStatus::REVIEW->value && $task->created_by !== $user->id,
            403,
            'Cuma pembuat tugas yang bisa menilai tugas yang sedang di-review.'
        );

        abort_if($task->approvalSnapshot?->status === 'in_review', 422, 'Tugas masih menunggu approval.');

        // Task tanpa review yang baru aja diselesaikan pelaksana (< 24 jam) boleh dikembalikan
        // creator ke IN_PROGRESS - "salah pencet"/butuh revisi lagi padahal ngga ada tahap review.
        if (
            $task->status === TaskStatus::COMPLETE->value
            && $newStatus === TaskStatus::IN_PROGRESS->value
            && ! $task->is_self_assigned
            && ! $task->needs_review
            && $task->created_by === $user->id
            && $task->completed_date
            && Carbon::parse($task->completed_date)->gt(Carbon::now()->subDay())
        ) {
            $this->taskRepository->update($task->id, ['status' => $newStatus, 'completed_date' => null, 'is_overdue' => false]);
            return;
        }

        abort_if($task->status === TaskStatus::COMPLETE->value, 422, 'Tugas yang sudah selesai tidak bisa dipindah lagi.');

        if ($task->status === TaskStatus::REVIEW->value && $newStatus === TaskStatus::IN_PROGRESS->value) {
            $this->taskRepository->update($task->id, ['status' => $newStatus]);
            $task->assignedTo?->notify(new TaskReviewDecided($task, false, $user->name));
            return;
        }

        $order = ($task->is_self_assigned || ! $task->needs_review) ? TaskStatus::BOARD_ORDER_SELF_ASSIGNED : TaskStatus::BOARD_ORDER;
        $currentIndex = array_search(TaskStatus::from($task->status), $order, true);
        $nextStatus = $order[$currentIndex + 1] ?? null;

        abort_unless($nextStatus?->value === $newStatus, 422, 'Tugas hanya bisa dipindah 1 langkah berurutan.');

        $updateData = ['status' => $newStatus];

        if ($newStatus === TaskStatus::COMPLETE->value) {
            $updateData['completed_date'] = Carbon::now();
            $updateData['is_overdue'] = $task->due_date && Carbon::now()->gt($task->due_date);
        }

        // Notifikasi opt-in ke creator (dicentang pas bikin tugas) - review diajukan, atau tugas
        // langsung diselesaikan pelaksana tanpa tahap review.
        if ($task->status === TaskStatus::IN_PROGRESS->value && $newStatus === TaskStatus::REVIEW->value && $task->notify_creator_on_review) {
            $task->createdBy?->notify(new TaskReviewRequested($task, $task->assignedTo?->name ?? '-'));
        }
        if ($task->status === TaskStatus::IN_PROGRESS->value && $newStatus === TaskStatus::COMPLETE->value && ! $task->is_self_assigned && $task->notify_creator_on_complete) {
            $task->createdBy?->notify(new TaskCompletedByAssignee($task, $task->assignedTo?->name ?? '-'));
        }

        // Creator approve dari REVIEW -> COMPLETE - kabari pelaksana, selalu (bukan opt-in, beda
        // dari 2 notif di atas yang emang sengaja opsional buat creator).
        if ($task->status === TaskStatus::REVIEW->value && $newStatus === TaskStatus::COMPLETE->value) {
            $task->assignedTo?->notify(new TaskReviewDecided($task, true, $user->name));
        }

        $this->taskRepository->update($task->id, $updateData);
    }

    public function toggleChecklistItem(TaskChecklistItem $item, User $user): void
    {
        $task = $item->task;
        abort_unless($task->assigned_to === $user->id || $task->created_by === $user->id, 403, 'Cuma pelaksana atau pembuat tugas yang bisa ceklis checklist ini.');

        $item->update(['is_done' => ! $item->is_done]);
    }

    /** Tambah item checklist ke task yang SUDAH ADA (beda dari checklist awal yang diisi pas
     * bikin task) - pelaksana boleh nambah (dia yang paling tahu detail kerjaannya), pembuat
     * juga boleh (termasuk atasan yang menugaskan ke bawahan di Penugasan Individu - dia
     * "pembuat" task itu meski bukan yang mengerjakan). Guard sama persis toggleChecklistItem(). */
    public function addChecklistItem(Task $task, string $title, User $user): TaskChecklistItem
    {
        abort_unless($task->assigned_to === $user->id || $task->created_by === $user->id, 403, 'Cuma pelaksana atau pembuat tugas yang bisa menambah checklist ini.');
        abort_if(trim($title) === '', 422, 'Judul checklist tidak boleh kosong.');

        return $task->checklistItems()->create(['title' => trim($title), 'created_by' => $user->id]);
    }

    /** Pelaksana boleh edit/hapus checklist yang dia buat sendiri, tapi TIDAK boleh utak-atik
     * checklist yang dibuat pemberi tugas (creator) - pemberi tugas yang menetapkan syarat kerja,
     * pelaksana cuma boleh nambah catatan kerjanya sendiri. Pemberi tugas sendiri boleh edit/hapus
     * item siapa pun di task-nya (tetap yang punya otoritas penuh). Item lama tanpa created_by
     * (sebelum kolom ini ada) dianggap milik pemberi tugas - default paling aman. */
    public function canModifyChecklistItem(TaskChecklistItem $item, User $user): bool
    {
        $task = $item->task;
        $isCreator = $task->created_by === $user->id;
        $isAssignee = $task->assigned_to === $user->id;

        if (! $isCreator && ! $isAssignee) {
            return false;
        }

        if ($isCreator) {
            return true;
        }

        $itemOwnerId = $item->created_by ?? $task->created_by;

        return $itemOwnerId === $user->id;
    }

    public function updateChecklistItem(TaskChecklistItem $item, string $title, User $user): void
    {
        abort_unless($this->canModifyChecklistItem($item, $user), 403, 'Kamu tidak bisa mengubah checklist ini.');
        abort_if(trim($title) === '', 422, 'Judul checklist tidak boleh kosong.');

        $item->update(['title' => trim($title)]);
    }

    public function deleteChecklistItem(TaskChecklistItem $item, User $user): void
    {
        abort_unless($this->canModifyChecklistItem($item, $user), 403, 'Kamu tidak bisa menghapus checklist ini.');

        $item->delete();
    }

    /** Rating bintang 1-5 dari creator ke pelaksana, opsional, cuma buat task yang udah COMPLETE. */
    public function rateTask(Task $task, int $rating, User $user): void
    {
        abort_unless($task->created_by === $user->id, 403, 'Cuma pembuat tugas yang bisa kasih rating.');
        abort_unless($task->status === TaskStatus::COMPLETE->value, 422, 'Tugas belum selesai.');
        abort_unless($rating >= 1 && $rating <= 5, 422, 'Rating harus 1-5.');

        $this->taskRepository->update($task->id, ['rating' => $rating]);
    }

    /** Task di dalam Project: semua anggota project boleh komentar (kolaboratif). Task pribadi:
     * cuma pelaksana/pembuat. */
    public function canCommentOnTask(Task $task, User $user): bool
    {
        if ($task->project_id) {
            return $this->canAccessProject($task->project, $user);
        }

        return in_array($user->id, [$task->assigned_to, $task->created_by], true);
    }

    public function addComment(Task $task, User $user, string $comment): void
    {
        abort_unless($this->canCommentOnTask($task, $user), 403, 'Kamu tidak bisa komentar di tugas ini.');

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

    /** $boardKey: 'todo' | 'saya' | 'individu' atau ULID TaskProject. */
    public function getBoardBackground(User $user, string $boardKey): ?TaskBoardBackground
    {
        return TaskBoardBackground::where('user_id', $user->id)->where('board_key', $boardKey)->first();
    }

    public function setBoardBackgroundColor(User $user, string $boardKey, string $hex): TaskBoardBackground
    {
        return TaskBoardBackground::updateOrCreate(
            ['user_id' => $user->id, 'board_key' => $boardKey],
            ['type' => 'color', 'value' => $hex]
        );
    }

    public function setBoardBackgroundImage(User $user, string $boardKey, UploadedFile $file): TaskBoardBackground
    {
        $old = $this->getBoardBackground($user, $boardKey);
        if ($old?->type === 'image') {
            Storage::disk('public')->delete($old->value);
        }

        $path = $file->store('tasks/board-backgrounds', 'public');

        return TaskBoardBackground::updateOrCreate(
            ['user_id' => $user->id, 'board_key' => $boardKey],
            ['type' => 'image', 'value' => $path]
        );
    }

    public function removeBoardBackground(User $user, string $boardKey): void
    {
        $bg = $this->getBoardBackground($user, $boardKey);
        if (! $bg) {
            return;
        }

        if ($bg->type === 'image') {
            Storage::disk('public')->delete($bg->value);
        }

        $bg->delete();
    }
}
