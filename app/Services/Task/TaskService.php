<?php

namespace App\Services\Task;

use App\Enum\Task\TaskSettingEnum;
use App\Enum\Task\TaskStatus;
use App\Models\Setting;
use App\Models\Task\Task;
use App\Models\Task\TaskAttachment;
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
use App\Repositories\Master\DepartmentRepository;
use App\Repositories\Task\TaskRepository;
use App\Services\OrgHierarchyService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(
        protected TaskRepository $taskRepository,
        protected DepartmentRepository $departmentRepository,
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
                abort_if($project->isArchived(), 422, 'Board ini sudah diarsipkan.');
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
            // eksplisit. Review di Board lewat List yang ditandai "Review" (bukan step-order status):
            // needs_review = task WAJIB lewat List Review dulu sebelum boleh masuk List Selesai.
            $needsReview = (bool) ($data['needs_review'] ?? false);
            if ($project && $needsReview) {
                abort_unless(
                    $project->lists()->where('is_review', true)->exists(),
                    422,
                    'Board ini belum punya List Review - tandai salah satu List sebagai Review dulu.'
                );
            }

            $listId = $data['list_id'] ?? null;
            if ($project && $listId) {
                // Tugas baru cuma boleh dibuat di List biasa - List Review/Selesai punya alurnya sendiri.
                $list = $project->lists()->find($listId);
                abort_unless($list, 422, 'List tidak ditemukan di Board ini.');
                abort_if($list->is_review || $list->is_final, 422, 'Tugas baru tidak bisa dibuat langsung di List Review/Selesai.');
            }
            if ($project && ! $listId) {
                $listId = $project->lists()->where('is_review', false)->where('is_final', false)->orderBy('position')->value('id')
                    ?? $project->lists()->orderBy('position')->value('id');
            }

            // Reviewer (siapa yang boleh kasih rating) cuma relevan untuk task di dalam Project -
            // creator boleh pilih anggota project lain jadi reviewer, bukan cuma dirinya sendiri.
            // Null = fallback ke creator sendiri (lihat rateTask()).
            $reviewerId = $data['reviewer_id'] ?? null;
            if ($reviewerId && $project) {
                abort_unless($this->canAccessProject($project, User::find($reviewerId)), 403, 'Orang itu bukan anggota project ini.');
            } elseif (! $project) {
                $reviewerId = null;
            }

            $task = $this->taskRepository->create([
                'code' => $this->generateCode($departmentId),
                'department_id' => $departmentId,
                'project_id' => $project?->id,
                'list_id' => $project ? $listId : null,
                'company_id' => $user->company_id,
                'requirement' => $data['requirement'],
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'priority' => $data['priority'] ?? 'medium',
                'due_date' => $data['due_date'] ?? null,
                'created_by' => $user->id,
                'reviewer_id' => $reviewerId,
                'assigned_to' => $assignedTo,
                'is_self_assigned' => $assignedTo === $user->id,
                'needs_review' => $needsReview,
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

    /**
     * Hapus Board - owner-only. Board tanpa satu pun tugas dihapus (soft-delete), Board yang
     * sudah punya tugas DIARSIPKAN (archived_at) supaya riwayatnya tetap bisa dilihat lagi di
     * halaman Arsip. Return 'deleted' | 'archived'.
     */
    public function removeProject(TaskProject $project, User $actor): string
    {
        abort_unless($this->isProjectOwner($project, $actor), 403);
        abort_if($project->isArchived(), 422, 'Board ini sudah diarsipkan.');

        if ($project->tasks()->exists()) {
            $project->update(['archived_at' => Carbon::now()]);

            return 'archived';
        }

        $project->delete();

        return 'deleted';
    }

    /** Pulihkan Board dari arsip - owner-only. */
    public function restoreProject(TaskProject $project, User $actor): void
    {
        abort_unless($this->isProjectOwner($project, $actor), 403);

        $project->update(['archived_at' => null]);
    }

    /** Board yang diikuti user dan sudah diarsipkan, terbaru diarsipkan di atas. */
    public function getArchivedProjects(User $user): Collection
    {
        return TaskProject::whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->whereNotNull('archived_at')
            ->withCount('tasks')
            ->orderByDesc('archived_at')
            ->get();
    }

    private function isProjectOwner(TaskProject $project, User $user): bool
    {
        return $project->members()->where('user_id', $user->id)->where('role', TaskProjectMember::ROLE_OWNER)->exists();
    }

    /** Bikin List baru di Board - owner-only. */
    public function createList(TaskProject $project, string $name, User $actor, ?string $color = null): TaskList
    {
        abort_unless($this->canManageProjectMembers($project, $actor), 403);

        abort_unless(TaskList::isValidColor($color), 422, 'Warna List tidak valid.');

        $position = ((int) $project->lists()->max('position')) + 1;

        return $project->lists()->create(['name' => $name, 'color' => $color, 'position' => $position]);
    }

    /** Ubah warna header List - owner-only. */
    public function updateListColor(TaskList $list, string $color, User $actor): void
    {
        abort_unless($this->canManageProjectMembers($list->project, $actor), 403);
        abort_unless(TaskList::isValidColor($color), 422, 'Warna List tidak valid.');

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
            $list->update(['is_final' => true, 'is_review' => false]);
        });
    }

    /**
     * Tandai/lepas tanda List "Review" (max 1 per Board, toggle) - owner-only. Card yang masuk List
     * ini dapat status=review; yang berhak memutuskan (reviewer ?? pembuat) lihat tombol
     * Revisi/Setujui di card-nya. List Selesai tidak boleh sekaligus jadi List Review.
     */
    public function toggleListReview(TaskList $list, User $actor): void
    {
        abort_unless($this->canManageProjectMembers($list->project, $actor), 403);
        abort_if($list->is_final, 422, 'List Selesai tidak bisa dijadikan List Review.');

        DB::transaction(function () use ($list) {
            $becomesReview = ! $list->is_review;

            if ($becomesReview) {
                TaskList::where('project_id', $list->project_id)->update(['is_review' => false]);
            }

            $list->update(['is_review' => $becomesReview]);
        });
    }

    /**
     * Keputusan review di List Review: setujui -> List Selesai (rating jalan seperti biasa), revisi
     * -> List sebelum List Review (fallback: List pertama). Lewat moveCardsAcrossLists() supaya
     * aturan siapa boleh memutuskan & notifikasi cuma ada di satu tempat.
     */
    public function decideBoardReview(Task $task, bool $approve, User $actor): void
    {
        $reviewList = $task->list;
        abort_unless($reviewList?->is_review, 422, 'Tugas ini tidak sedang di List Review.');

        $others = $reviewList->project->lists()->where('is_review', false)->where('is_final', false);

        $target = $approve
            ? $reviewList->project->lists()->where('is_final', true)->first()
            : ((clone $others)->where('position', '<', $reviewList->position)->orderByDesc('position')->first()
                ?? (clone $others)->orderBy('position')->first());

        abort_unless($target, 422, $approve ? 'Board ini belum punya List Selesai.' : 'Tidak ada List tujuan untuk revisi.');

        $this->moveCardsAcrossLists([$target->id => [$task->id]], $actor);
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
    public function moveCardsAcrossLists(array $listsPayload, User $actor, ?string $movedTaskId = null): void
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
        abort_if($project->isArchived(), 422, 'Board ini sudah diarsipkan.');

        $isOwner = $this->isProjectOwner($project, $actor);

        DB::transaction(function () use ($listsPayload, $lists, $actor, $movedTaskId, $isOwner) {
            foreach ($listsPayload as $listId => $taskIds) {
                $list = $lists->get($listId);

                foreach (array_values($taskIds) as $index => $taskId) {
                    $task = Task::find($taskId);
                    if (! $task) {
                        continue;
                    }

                    $updateData = ['list_id' => $listId, 'position' => $index];

                    // Siapa yang boleh menggeser kartu: di List kerja cuma yang ditugaskan & pembuat Board
                    // (owner); di List Review cuma yang berhak memutuskan (reviewer ?? pembuat tugas).
                    // Yang dicek cuma kartu yang DIGESER (movedTaskId) - kartu lain di payload cuma ikut
                    // bergeser posisinya.
                    $fromReview = (bool) $task->list?->is_review;
                    $isMoved = $movedTaskId ? $task->id === $movedTaskId : $task->list_id !== $listId;
                    if ($isMoved) {
                        if ($fromReview) {
                            abort_unless(
                                ($task->reviewer_id ?? $task->created_by) === $actor->id,
                                403,
                                'Cuma pembuat/reviewer tugas yang bisa memutuskan tugas di List Review.'
                            );
                        } else {
                            abort_unless(
                                $task->assigned_to === $actor->id || $isOwner,
                                403,
                                'Cuma yang ditugaskan atau pembuat Board yang bisa memindahkan tugas ini.'
                            );
                        }
                    }

                    if ($task->list_id !== $listId) {
                        if ($list->is_review) {
                            $updateData['status'] = TaskStatus::REVIEW->value;
                            $updateData['completed_date'] = null;
                            $updateData['is_overdue'] = false;

                            // Kabari yang berhak memutuskan (reviewer ?? pembuat) - kecuali dia sendiri yang memindahkan.
                            $deciderId = $task->reviewer_id ?? $task->created_by;
                            if ($deciderId !== $actor->id && ($task->needs_review || $task->notify_creator_on_review)) {
                                User::find($deciderId)?->notify(new TaskReviewRequested($task, $task->assignedTo?->name ?? '-'));
                            }
                        } elseif ($list->is_final) {
                            abort_if(
                                // Kalau tanda List Review sudah dilepas dari Board, jangan bikin tugas macet.
                                $task->needs_review && ! $fromReview && $list->project->lists()->where('is_review', true)->exists(),
                                422,
                                'Tugas ini wajib direview - pindahkan ke List Review dulu, dinilai reviewer, baru bisa Selesai.'
                            );

                            $updateData['status'] = TaskStatus::COMPLETE->value;
                            $updateData['completed_date'] = Carbon::now();
                            $updateData['is_overdue'] = $task->due_date && Carbon::now()->gt($task->due_date);

                            if ($fromReview) {
                                $task->assigned_to !== $actor->id && $task->assignedTo?->notify(new TaskReviewDecided($task, true, $actor->name));
                            } elseif ($task->notify_creator_on_complete && $task->assigned_to !== $task->created_by && $task->created_by !== $actor->id) {
                                $task->createdBy?->notify(new TaskCompletedByAssignee($task, $task->assignedTo?->name ?? '-'));
                            }
                        } elseif ($task->list?->is_final || $fromReview) {
                            $updateData['status'] = TaskStatus::IN_PROGRESS->value;
                            $updateData['completed_date'] = null;
                            $updateData['is_overdue'] = false;

                            if ($fromReview) {
                                $task->assigned_to !== $actor->id && $task->assignedTo?->notify(new TaskReviewDecided($task, false, $actor->name));
                            }
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

    /** Owner Board aktif - Board yang diarsipkan cuma bisa dilihat, tidak bisa dikelola. */
    public function canManageProjectMembers(TaskProject $project, User $user): bool
    {
        if ($project->isArchived()) {
            return false;
        }

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
            ->whereNull('archived_at')
            ->withCount('members')
            ->orderBy('name')
            ->get();
    }

    /** Tugas Selesai yang sudah lewat H+1 masuk Riwayat (sama dengan Todo/Tugas Saya/Individu). */
    public function getProjectHistoryTasks(TaskProject $project, int $perPage = 10)
    {
        return $project->tasks()
            ->with(['assignedTo', 'createdBy'])
            ->where('status', TaskStatus::COMPLETE->value)
            ->where('completed_date', '<=', Carbon::now()->subDay())
            ->orderByDesc('completed_date')
            ->paginate($perPage);
    }

    public function getProjectTasks(TaskProject $project): Collection
    {
        return $project->tasks()
            ->where(function ($q) {
                $q->where('status', '!=', TaskStatus::COMPLETE->value)
                    ->orWhereNull('completed_date')
                    ->orWhere('completed_date', '>', Carbon::now()->subDay());
            })
            ->with(['assignedTo.profile', 'createdBy', 'checklistItems', 'comments.user', 'list'])
            ->withCount('attachments')
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();
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

    /** Angka badge di tab nav (mis. "Tugas Saya") - jumlah task scope itu yang belum COMPLETE. */
    public function countIncomplete(User $user, string $scope): int
    {
        return $this->scopedTaskQuery($user, $scope)->where('status', '!=', TaskStatus::COMPLETE->value)->count();
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
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
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

        // Todo Saya (self-assigned) bebas bolak-balik Belum Dikerjakan <-> Sedang Dalam Proses -
        // catatan pribadi, nggak ada pihak lain yang perlu dijaga urutan langkahnya.
        if (
            $task->is_self_assigned
            && in_array($task->status, [TaskStatus::TODO->value, TaskStatus::IN_PROGRESS->value], true)
            && in_array($newStatus, [TaskStatus::TODO->value, TaskStatus::IN_PROGRESS->value], true)
            && $task->status !== $newStatus
        ) {
            $this->taskRepository->update($task->id, ['status' => $newStatus]);
            return;
        }

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
        abort_unless($this->canContributeToTask($task, $user), 403, 'Cuma pelaksana, pembuat tugas, atau pembuat Board yang bisa ceklis checklist ini.');

        $item->update(['is_done' => ! $item->is_done]);
    }

    /** Tambah item checklist ke task yang SUDAH ADA (beda dari checklist awal yang diisi pas
     * bikin task) - pelaksana boleh nambah (dia yang paling tahu detail kerjaannya), pembuat
     * juga boleh (termasuk atasan yang menugaskan ke bawahan di Penugasan Individu - dia
     * "pembuat" task itu meski bukan yang mengerjakan). Guard sama persis toggleChecklistItem(). */
    public function addChecklistItem(Task $task, string $title, User $user): TaskChecklistItem
    {
        abort_unless($this->canContributeToTask($task, $user), 403, 'Cuma pelaksana, pembuat tugas, atau pembuat Board yang bisa menambah checklist ini.');
        abort_if(trim($title) === '', 422, 'Judul checklist tidak boleh kosong.');

        return $task->checklistItems()->create(['title' => trim($title), 'created_by' => $user->id]);
    }

    /** Pelaksana boleh edit/hapus checklist yang dia buat sendiri, tapi TIDAK boleh utak-atik
     * checklist yang dibuat pemberi tugas (creator) - pemberi tugas yang menetapkan syarat kerja,
     * pelaksana cuma boleh nambah catatan kerjanya sendiri. Pemberi tugas sendiri boleh edit/hapus
     * item siapa pun di task-nya (tetap yang punya otoritas penuh). Item lama tanpa created_by
     * (sebelum kolom ini ada) dianggap milik pemberi tugas - default paling aman. */
    /**
     * Yang boleh mengelola isi tugas (checklist, lampiran): pelaksana, pembuat tugas (assigner),
     * dan - khusus tugas Board - pembuat/owner Board. Board yang diarsipkan cuma bisa dilihat.
     */
    public function canContributeToTask(Task $task, User $user): bool
    {
        if ($task->project_id) {
            $project = $task->project;

            if ($project?->isArchived()) {
                return false;
            }

            if ($project && $this->isProjectOwner($project, $user)) {
                return true;
            }
        }

        return $task->assigned_to === $user->id || $task->created_by === $user->id;
    }

    public function canModifyChecklistItem(TaskChecklistItem $item, User $user): bool
    {
        $task = $item->task;
        $isBoardOwner = $task->project_id && $task->project && ! $task->project->isArchived() && $this->isProjectOwner($task->project, $user);
        $isCreator = $task->created_by === $user->id || $isBoardOwner;
        $isAssignee = $task->assigned_to === $user->id;

        if (! $this->canContributeToTask($task, $user)) {
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
        // reviewer_id (dipilih creator saat bikin task di Project) yang berhak rating kalau
        // diisi - fallback ke creator sendiri (task pribadi, atau project task tanpa reviewer
        // dipilih eksplisit).
        $allowedRaterId = $task->reviewer_id ?? $task->created_by;
        abort_unless($allowedRaterId === $user->id, 403, 'Kamu tidak berhak kasih rating untuk tugas ini.');
        abort_if($task->assigned_to === $user->id, 422, 'Tidak bisa menilai tugas yang kamu kerjakan sendiri.');
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

        // Komentar baru cuma dikabari ke pelaksana (bukan pembuat/anggota lain) - kecuali dia sendiri penulisnya.
        if ($task->assigned_to && $task->assigned_to !== $user->id) {
            User::find($task->assigned_to)?->notify(new TaskCommented($task, $user->name, $comment));
        }
    }

    public function addFileAttachment(Task $task, User $user, UploadedFile $file, ?string $description = null): void
    {
        abort_unless($this->canContributeToTask($task, $user), 403, 'Cuma pelaksana, pembuat tugas, atau pembuat Board yang bisa menambah lampiran.');

        // Gambar (jpg/png/webp) otomatis diperkecil & dikompres sebelum disimpan; file lain apa adanya.
        $compressed = \App\Support\ImageCompressor::compress($file);
        if ($compressed) {
            $path = 'tasks/attachments/' . \Illuminate\Support\Str::random(40) . '.' . $compressed['extension'];
            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $compressed['binary']);
        } else {
            $path = $file->store('tasks/attachments', 'public');
        }

        $this->taskRepository->addAttachment($task->id, [
            'type' => TaskAttachment::TYPE_FILE,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'description' => filled($description) ? trim($description) : null,
            'uploaded_by' => $user->id,
        ]);
    }

    public function addLinkAttachment(Task $task, User $user, string $url, ?string $label, ?string $description = null): void
    {
        abort_unless($this->canContributeToTask($task, $user), 403, 'Cuma pelaksana, pembuat tugas, atau pembuat Board yang bisa menambah lampiran.');

        $this->taskRepository->addAttachment($task->id, [
            'type' => TaskAttachment::TYPE_LINK,
            'url' => $url,
            'file_name' => $label ?: $url,
            'description' => filled($description) ? trim($description) : null,
            'uploaded_by' => $user->id,
        ]);
    }

    public function removeAttachment(string $attachmentId, User $user): void
    {
        $attachment = TaskAttachment::findOrFail($attachmentId);
        abort_unless($this->canContributeToTask(Task::findOrFail($attachment->task_id), $user), 403, 'Cuma pelaksana, pembuat tugas, atau pembuat Board yang bisa menghapus lampiran.');

        $this->taskRepository->deleteAttachment($attachmentId);
    }

}
