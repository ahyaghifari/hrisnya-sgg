<?php

namespace App\Livewire\Task;

use App\Enum\Task\TaskPriority;
use App\Enum\Task\TaskStatus;
use App\Models\Task\Task;
use App\Models\Task\TaskChecklistItem;
use App\Models\Task\TaskList;
use App\Models\Task\TaskProject;
use App\Models\User;
use App\Services\OrgHierarchyService;
use App\Services\Task\TaskService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Halaman "Tugas" portal — pengganti modul Kanban. Board/List cuma mode tampilan atas
 * task_records yang sudah bisa dilihat user (assigned_to/created_by/bawahan/anggota project),
 * project sekarang entitas ringan (task_projects + task_project_members) — sidebar khusus
 * halaman ini, lihat planning/cached-dreaming-ladybug.md.
 */
#[Layout('components.layout', ['title' => 'Tugas'])]
class TaskListPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    // #[Url] biar notifikasi (klik push notif TaskAssigned/dst) bisa deep-link langsung ke tab
    // yang benar, mis. /tugas?scope=saya - lihat public/sw.js notificationclick.
    #[Url]
    public string $scope = 'todo'; // todo | saya | individu | tim - lihat TaskService::getPortalTasks()
    public string $search = ''; // filter board by kebutuhan/nama assignee

    #[Url]
    public ?string $activeProjectId = null;

    // Modal "Tugas Baru"
    public bool $showTaskModal = false;
    public ?string $requirement = null;
    public ?string $due_date = null;
    public string $priority = 'medium';
    public ?string $assigned_to = null;
    public ?string $newTaskListId = null; // List tujuan tugas baru (Board)
    public array $checklist = [];
    // Reviewer (siapa yang boleh kasih rating) - cuma relevan buat task di dalam Project, null =
    // fallback ke creator sendiri (lihat TaskService::rateTask()).
    public ?string $reviewerId = null;
    // "Butuh Approval Saya" - opt-in, kalau dicentang task wajib mampir REVIEW sebelum COMPLETE
    // (creator minta ditinjau ulang sebelum beneran selesai). Lepas dari alur approval formal
    // (MasterApprovalFlows/service_id) - itu punya jalur sendiri, sengaja dilepas dari modal
    // quick-create ini (2026-08-02).
    public bool $needsReview = false;
    // Relevan cuma kalau needsReview true: kasih tahu creator pas pelaksana ajukan review.
    public bool $notifyOnReview = false;
    // Relevan cuma kalau needsReview false: kasih tahu creator pas pelaksana langsung selesaikan.
    public bool $notifyOnComplete = false;

    // Modal detail task
    public ?string $selectedTaskId = null;
    public string $newComment = '';
    public string $newChecklistTitle = '';
    public ?string $editingChecklistItemId = null;
    public string $editingChecklistItemTitle = '';

    // Modal "Edit Tugas"
    public bool $showEditTaskModal = false;
    public ?string $editTaskId = null;
    public string $editRequirement = '';
    public ?string $editDueDate = null;
    public string $editPriority = 'medium';

    // Lampiran - inline di modal detail (bukan modal terpisah lagi, 2026-08-02, ala Trello)
    public $newAttachmentFile = null;
    public string $newAttachmentLink = '';
    public string $newAttachmentLinkLabel = '';

    // Modal "Kelola Anggota"
    public bool $showMembersModal = false;
    public ?string $newProjectMemberId = null;

    // List custom Board - form tambah List baru + inline rename (dua-duanya owner-only, dicek
    // ulang di TaskService, ini cuma state form).
    public string $newListName = '';
    public string $newListColor = 'navy';
    public ?string $editingListId = null;
    public string $editingListName = '';

    /**
     * $scope/$project vienen de route param (/tugas/papan/{scope} atau /tugas/papan/board/{project})
     * - TaskMenuPage (halaman menu /tugas) yang navigasi ke sini, bukan tab switcher inline lagi
     * (dihapus 2026-09-19, lihat planning wiggly-cooking-star). #[Url] tetap dipertahankan biar
     * deep-link lama (?scope=saya, dipakai push notification) masih jalan.
     */
    public function mount(?string $scope = null, ?TaskProject $project = null): void
    {
        if ($scope) {
            $this->scope = $scope;
        }

        if ($project) {
            abort_unless(app(TaskService::class)->canAccessProject($project, Auth::user()), 403);
            $this->activeProjectId = $project->id;
        }

        if ($this->activeProjectId && ! app(TaskService::class)->canAccessProject(TaskProject::findOrFail($this->activeProjectId), Auth::user())) {
            $this->activeProjectId = null;
        }
    }

    /** Angka di pill nav "Tugas Saya" - jumlah tugas yang belum selesai. */
    public function getMyTaskPendingCountProperty(): int
    {
        return app(TaskService::class)->countIncomplete(Auth::user(), 'saya');
    }

    public function getHasSubordinatesProperty(): bool
    {
        return app(OrgHierarchyService::class)->subordinates(Auth::user())->exists();
    }

    public function getSubordinatesProperty()
    {
        if (! $this->hasSubordinates) {
            return collect();
        }

        return app(OrgHierarchyService::class)->subordinates(Auth::user())->get(['id', 'name']);
    }

    public function getMyProjectsProperty()
    {
        return app(TaskService::class)->getMyProjects(Auth::user());
    }

    public function getActiveProjectProperty(): ?TaskProject
    {
        if (! $this->activeProjectId) {
            return null;
        }

        // Board yang diarsipkan tidak ada di daftar aktif tapi masih bisa dibuka (read-only) dari Arsip.
        return $this->myProjects->firstWhere('id', $this->activeProjectId)
            ?? app(TaskService::class)->getArchivedProjects(Auth::user())->firstWhere('id', $this->activeProjectId);
    }

    public function getIsProjectOwnerProperty(): bool
    {
        return $this->activeProject
            && $this->activeProject->members()->where('user_id', Auth::id())->where('role', \App\Models\Task\TaskProjectMember::ROLE_OWNER)->exists();
    }

    public function getIsProjectArchivedProperty(): bool
    {
        return (bool) $this->activeProject?->isArchived();
    }

    public function getProjectMembersProperty()
    {
        if (! $this->activeProject) {
            return collect();
        }

        return $this->activeProject->members()->with('user')->get();
    }

    public function getCompanyUsersProperty()
    {
        $existingIds = $this->activeProject ? $this->projectMembers->pluck('user_id') : collect();

        return User::query()
            ->where('company_id', Auth::user()->company_id)
            ->whereNotIn('id', $existingIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getCanManageProjectMembersProperty(): bool
    {
        if (! $this->activeProject) {
            return false;
        }

        return app(TaskService::class)->canManageProjectMembers($this->activeProject, Auth::user());
    }

    /** Owner atau anggota yang ditandai "Assigner" - boleh menugaskan ke anggota Board lain. */
    public function getCanAssignInProjectProperty(): bool
    {
        if (! $this->activeProject) {
            return false;
        }

        return app(TaskService::class)->canAssignInProject($this->activeProject, Auth::user());
    }

    public function getTasksProperty()
    {
        $service = app(TaskService::class);

        $tasks = $this->activeProject
            ? $service->getProjectTasks($this->activeProject)
            : $service->getPortalTasks(Auth::user(), $this->scope)
                ->load(['assignedTo.profile', 'createdBy', 'checklistItems', 'comments.user'])
                ->loadCount('attachments');

        // Search cuma aktif buat tab Penugasan Individu.
        $search = ($this->activeProject || $this->scope !== 'individu') ? '' : trim($this->search);
        if ($search === '') {
            return $tasks;
        }

        return $tasks->filter(fn ($task) => str_contains(strtolower($task->requirement ?? ''), strtolower($search))
            || str_contains(strtolower($task->assignedTo?->name ?? ''), strtolower($search)))->values();
    }

    public function getTasksByStatusProperty(): array
    {
        $tasks = $this->tasks;

        return collect(TaskStatus::cases())
            ->mapWithKeys(fn (TaskStatus $status) => [$status->value => $tasks->where('status', $status->value)->values()])
            ->all();
    }

    /** Kolom List custom Board (Trello-style) - analog tasksByStatus, cuma aktif kalau di Board. */
    public function getTasksByListProperty(): array
    {
        if (! $this->activeProject) {
            return [];
        }

        $tasks = $this->tasks;

        return $this->activeProject->lists
            ->mapWithKeys(fn (TaskList $list) => [$list->id => $tasks->where('list_id', $list->id)->values()])
            ->all();
    }

    /** Riwayat (task COMPLETE H+1 ke atas) - cuma buat scope todo/saya/individu, bukan project. */
    public function getHistoryTasksProperty()
    {
        if ($this->activeProject) {
            return app(TaskService::class)->getProjectHistoryTasks($this->activeProject);
        }

        return app(TaskService::class)->getHistoryTasks(Auth::user(), $this->scope);
    }

    /** $listId: dari tombol "+ Tambah tugas" di List Board - tugas baru langsung masuk List itu. */
    public function openCreateTask(?string $listId = null): void
    {
        $this->reset(['requirement', 'due_date', 'assigned_to', 'checklist', 'newChecklistTitle', 'reviewerId']);
        $this->newTaskListId = $this->activeProject ? $listId : null;
        $this->priority = TaskPriority::MEDIUM->value;
        $this->needsReview = false;
        $this->notifyOnReview = false;
        $this->notifyOnComplete = false;
        $this->showTaskModal = true;
    }

    public function closeTaskModal(): void
    {
        $this->showTaskModal = false;
    }

    public function addChecklistDraft(): void
    {
        if (trim($this->newChecklistTitle) === '') {
            return;
        }

        $this->checklist[] = $this->newChecklistTitle;
        $this->newChecklistTitle = '';
    }

    public function removeChecklistDraft(int $index): void
    {
        unset($this->checklist[$index]);
        $this->checklist = array_values($this->checklist);
    }

    /** Assign wajib pilih orang (ngga boleh "saya sendiri") pas nugasin ke bawahan, bukan project. */
    protected function assignedToRequiredForSubordinate(): bool
    {
        return $this->hasSubordinates && ! $this->activeProject && $this->scope !== 'todo';
    }

    protected function taskRules(): array
    {
        return [
            'requirement' => ['required', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:' . implode(',', array_column(TaskPriority::cases(), 'value'))],
            'assigned_to' => [$this->assignedToRequiredForSubordinate() ? 'required' : 'nullable', 'string'],
        ];
    }

    public function createTask(): void
    {
        $data = $this->validate($this->taskRules());

        try {
            app(TaskService::class)->createCasualTask([
                'requirement' => $data['requirement'],
                'priority' => $data['priority'],
                'due_date' => $data['due_date'],
                // Di Board, non-Assigner cuma boleh bikin tugas untuk dirinya sendiri (service juga
                // menolak, ini supaya nilai sisa dari form nggak bikin error membingungkan).
                'assigned_to' => ($this->activeProject && ! $this->canAssignInProject) ? null : ($data['assigned_to'] ?? null),
                'project_id' => $this->activeProjectId,
                'list_id' => $this->activeProjectId ? $this->newTaskListId : null,
                'reviewer_id' => ($this->activeProject && ! $this->canAssignInProject) ? null : $this->reviewerId,
                'needs_review' => ($this->activeProject && ! $this->canAssignInProject) ? false : $this->needsReview,
                'notify_creator_on_review' => $this->needsReview && $this->notifyOnReview,
                'notify_creator_on_complete' => ! $this->needsReview && $this->notifyOnComplete,
                'checklist' => $this->checklist,
            ]);

            Notification::make()->title('Tugas berhasil dibuat')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa membuat tugas ini')->danger()->send();
            return;
        }

        $this->showTaskModal = false;
        unset($this->tasks);
    }

    public function moveTaskStatus(string $taskId, string $status): void
    {
        $task = Task::findOrFail($taskId);

        // Task di List Review Board: keputusan review harus mindahin card antar List juga,
        // bukan cuma ganti status - lewat decideBoardReview().
        if ($task->list_id && $task->status === TaskStatus::REVIEW->value
            && in_array($status, [TaskStatus::COMPLETE->value, TaskStatus::IN_PROGRESS->value], true)) {
            $this->decideBoardReview($taskId, $status === TaskStatus::COMPLETE->value);

            return;
        }

        try {
            app(TaskService::class)->moveStatus($task, $status, Auth::user());

            // Baru selesai & belum dirating - buka modal detail otomatis buat yang berhak rating
            // (reviewer kalau dipilih, fallback ke creator) supaya langsung bisa kasih rating
            // (wajib - modal nggak bisa ditutup sebelum dirating, lihat closeTaskDetail).
            if ($status === TaskStatus::COMPLETE->value) {
                $this->dispatch('task-completed');
            }

            if ($status === TaskStatus::COMPLETE->value && $task->refresh()->requiresRatingFrom(Auth::id())) {
                $this->openTaskDetail($taskId);
            }
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa memindahkan tugas ini')->danger()->send();
        }

        unset($this->tasks, $this->historyTasks);
    }

    public function deleteTask(string $taskId): void
    {
        try {
            app(TaskService::class)->deleteTask(Task::findOrFail($taskId), Auth::user());
            $this->selectedTaskId = null;
            Notification::make()->title('Tugas dihapus')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menghapus tugas ini')->danger()->send();
        }

        unset($this->tasks, $this->historyTasks);
    }

    public function openEditTask(string $taskId): void
    {
        $task = Task::findOrFail($taskId);

        $this->editTaskId = $task->id;
        $this->editRequirement = $task->requirement ?? '';
        $this->editDueDate = $task->due_date;
        $this->editPriority = $task->priority?->value ?? TaskPriority::MEDIUM->value;
        $this->showEditTaskModal = true;
    }

    public function closeEditTaskModal(): void
    {
        $this->showEditTaskModal = false;
        $this->editTaskId = null;
    }

    public function updateTaskDetails(): void
    {
        $data = $this->validate([
            'editRequirement' => ['required', 'string'],
            'editDueDate' => ['nullable', 'date'],
            'editPriority' => ['required', 'in:' . implode(',', array_column(TaskPriority::cases(), 'value'))],
        ]);

        try {
            app(TaskService::class)->updateTaskDetails(Task::findOrFail($this->editTaskId), [
                'requirement' => $data['editRequirement'],
                'due_date' => $data['editDueDate'],
                'priority' => $data['editPriority'],
            ], Auth::user());

            Notification::make()->title('Tugas berhasil diubah')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa mengubah tugas ini')->danger()->send();
            return;
        }

        $this->showEditTaskModal = false;
        $this->editTaskId = null;
        unset($this->tasks, $this->selectedTask);
    }

    public function openTaskDetail(string $taskId): void
    {
        $this->selectedTaskId = $taskId;
        $this->newComment = '';
        $this->newChecklistTitle = '';
        $this->editingChecklistItemId = null;
        $this->editingChecklistItemTitle = '';
    }

    public function closeTaskDetail(): void
    {
        $task = $this->selectedTask;
        if ($task && $task->requiresRatingFrom(Auth::id())) {
            Notification::make()->title('Berikan penilaian dulu sebelum menutup tugas ini')->warning()->send();

            return;
        }

        $this->selectedTaskId = null;
        // $this->selectedTask di atas sudah ke-cache (computed) dengan task lama - buang, kalau tidak modal tetap ke-render.
        unset($this->selectedTask);
    }

    public function getSelectedTaskProperty(): ?Task
    {
        if (! $this->selectedTaskId) {
            return null;
        }

        return Task::with(['assignedTo', 'createdBy', 'reviewer', 'checklistItems', 'comments.user', 'attachments', 'proofs', 'rejectionNoteBy'])
            ->find($this->selectedTaskId);
    }

    public function toggleChecklistItem(string $itemId): void
    {
        $item = TaskChecklistItem::findOrFail($itemId);

        try {
            app(TaskService::class)->toggleChecklistItem($item, Auth::user());
            unset($this->selectedTask, $this->tasks);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa mengubah checklist ini')->danger()->send();
        }
    }

    public function addChecklistItemToTask(string $taskId): void
    {
        if (trim($this->newChecklistTitle) === '') {
            return;
        }

        try {
            app(TaskService::class)->addChecklistItem(Task::findOrFail($taskId), $this->newChecklistTitle, Auth::user());
            $this->newChecklistTitle = '';
            unset($this->selectedTask, $this->tasks);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menambah checklist ini')->danger()->send();
        }
    }

    public function startEditChecklistItem(string $itemId, string $currentTitle): void
    {
        $this->editingChecklistItemId = $itemId;
        $this->editingChecklistItemTitle = $currentTitle;
    }

    public function cancelEditChecklistItem(): void
    {
        $this->editingChecklistItemId = null;
        $this->editingChecklistItemTitle = '';
    }

    public function updateChecklistItem(): void
    {
        if (! $this->editingChecklistItemId) {
            return;
        }

        try {
            $item = TaskChecklistItem::findOrFail($this->editingChecklistItemId);
            app(TaskService::class)->updateChecklistItem($item, $this->editingChecklistItemTitle, Auth::user());
            unset($this->selectedTask, $this->tasks);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa mengubah checklist ini')->danger()->send();
        }

        $this->editingChecklistItemId = null;
        $this->editingChecklistItemTitle = '';
    }

    public function deleteChecklistItem(string $itemId): void
    {
        try {
            $item = TaskChecklistItem::findOrFail($itemId);
            app(TaskService::class)->deleteChecklistItem($item, Auth::user());
            unset($this->selectedTask, $this->tasks);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menghapus checklist ini')->danger()->send();
        }
    }

    public function rateTask(string $taskId, int $rating): void
    {
        try {
            app(TaskService::class)->rateTask(Task::findOrFail($taskId), $rating, Auth::user());
            unset($this->selectedTask, $this->tasks);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa memberi rating')->danger()->send();
        }
    }

    public function addComment(string $taskId): void
    {
        if (trim($this->newComment) === '') {
            return;
        }

        try {
            app(TaskService::class)->addComment(Task::findOrFail($taskId), Auth::user(), $this->newComment);
            $this->newComment = '';
            unset($this->tasks, $this->selectedTask);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa mengirim komentar')->danger()->send();
        }
    }

    public function uploadFileAttachment(): void
    {
        if (! $this->newAttachmentFile || ! $this->selectedTask || ($this->selectedTask->is_self_assigned && ! $this->selectedTask->project_id)) {
            return;
        }

        $this->validate(['newAttachmentFile' => ['file', 'max:10240']]);

        try {
            app(TaskService::class)->addFileAttachment($this->selectedTask, Auth::user(), $this->newAttachmentFile);
            $this->reset(['newAttachmentFile']);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menambah lampiran')->danger()->send();
        }

        unset($this->selectedTask, $this->tasks);
    }

    public function addLinkAttachment(): void
    {
        if (trim($this->newAttachmentLink) === '' || ! $this->selectedTask || ($this->selectedTask->is_self_assigned && ! $this->selectedTask->project_id)) {
            return;
        }

        $this->validate(['newAttachmentLink' => ['url']]);

        try {
            app(TaskService::class)->addLinkAttachment($this->selectedTask, Auth::user(), $this->newAttachmentLink, $this->newAttachmentLinkLabel ?: null);
            $this->reset(['newAttachmentLink', 'newAttachmentLinkLabel']);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menambah lampiran')->danger()->send();
        }

        unset($this->selectedTask, $this->tasks);
    }

    public function removeAttachment(string $attachmentId): void
    {
        try {
            app(TaskService::class)->removeAttachment($attachmentId, Auth::user());
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menghapus lampiran')->danger()->send();
        }

        unset($this->selectedTask, $this->tasks);
    }

    public function createList(): void
    {
        if (! $this->activeProject || trim($this->newListName) === '') {
            return;
        }

        try {
            app(TaskService::class)->createList($this->activeProject, trim($this->newListName), Auth::user(), $this->newListColor);
            $this->newListName = '';
            $this->newListColor = 'navy';
            unset($this->myProjects, $this->tasks);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa membuat List')->danger()->send();
        }
    }

    public function changeListColor(string $listId, string $color): void
    {
        try {
            app(TaskService::class)->updateListColor(TaskList::findOrFail($listId), $color, Auth::user());
            unset($this->myProjects);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa mengubah warna List')->danger()->send();
        }
    }

    public function startEditList(string $listId): void
    {
        $list = TaskList::findOrFail($listId);
        $this->editingListId = $listId;
        $this->editingListName = $list->name;
    }

    public function cancelEditList(): void
    {
        $this->editingListId = null;
        $this->editingListName = '';
    }

    public function renameList(): void
    {
        if (! $this->editingListId || trim($this->editingListName) === '') {
            return;
        }

        try {
            app(TaskService::class)->renameList(TaskList::findOrFail($this->editingListId), trim($this->editingListName), Auth::user());
            unset($this->myProjects);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa mengubah nama List')->danger()->send();
        }

        $this->editingListId = null;
        $this->editingListName = '';
    }

    public function deleteList(string $listId): void
    {
        try {
            app(TaskService::class)->deleteList(TaskList::findOrFail($listId), Auth::user());
            unset($this->myProjects, $this->tasks);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menghapus List')->danger()->send();
        }
    }

    public function markListFinal(string $listId): void
    {
        try {
            app(TaskService::class)->setListFinal(TaskList::findOrFail($listId), Auth::user());
            unset($this->myProjects);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menandai List ini')->danger()->send();
        }
    }

    public function markListReview(string $listId): void
    {
        try {
            app(TaskService::class)->toggleListReview(TaskList::findOrFail($listId), Auth::user());
            unset($this->myProjects);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menandai List ini')->danger()->send();
        }
    }

    public function decideBoardReview(string $taskId, bool $approve): void
    {
        try {
            $task = Task::findOrFail($taskId);
            app(TaskService::class)->decideBoardReview($task, $approve, Auth::user());

            if ($approve) {
                $this->dispatch('task-completed');

                // Baru disetujui & belum dirating - buka modal detail, rating wajib (lihat closeTaskDetail).
                if ($task->refresh()->requiresRatingFrom(Auth::id())) {
                    $this->openTaskDetail($taskId);
                }
            }
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa memutuskan review tugas ini')->danger()->send();
        }

        unset($this->tasks, $this->historyTasks);
    }

    /** Pengganti drag-drop (dihapus, layout Board sekarang list vertikal) - pindah 1 kartu ke List lain. */
    public function moveTaskToList(string $taskId, string $listId): void
    {
        try {
            app(TaskService::class)->moveCardsAcrossLists([$listId => [$taskId]], Auth::user(), $taskId);

            $task = Task::find($taskId);
            $list = TaskList::find($listId);
            if ($task && $list?->is_final && $task->requiresRatingFrom(Auth::id())) {
                $this->openTaskDetail($taskId);
            }
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa memindahkan tugas ini')->danger()->send();
        }

        unset($this->tasks);
    }

    public function moveListLeft(string $listId): void
    {
        $this->swapListPosition($listId, -1);
    }

    public function moveListRight(string $listId): void
    {
        $this->swapListPosition($listId, 1);
    }

    private function swapListPosition(string $listId, int $direction): void
    {
        if (! $this->activeProject) {
            return;
        }

        $ids = $this->activeProject->lists->pluck('id')->values()->all();
        $index = array_search($listId, $ids, true);

        if ($index === false) {
            return;
        }

        $swapWith = $index + $direction;
        if ($swapWith < 0 || $swapWith >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$swapWith]] = [$ids[$swapWith], $ids[$index]];

        try {
            app(TaskService::class)->reorderLists($this->activeProject, $ids, Auth::user());
            unset($this->myProjects);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa mengurutkan List')->danger()->send();
        }
    }

    /**
     * Dipanggil dari drag-and-drop (SortableJS) - $listsPayload: [list_id => [task_id,...], ...].
     * Reuse logic auto-buka modal rating yang sudah ada di moveTaskStatus() (task yang baru saja
     * COMPLETE, actor creator, belum ada rating).
     */
    public function moveCardsAcrossLists(array $listsPayload, ?string $movedTaskId = null): void
    {
        // List asal tiap task sebelum dipindah - buat tahu mana yang BARU masuk List Selesai (confetti).
        $listBefore = Task::whereIn('id', array_merge(...array_map('array_values', array_values($listsPayload))))->pluck('list_id', 'id');

        try {
            app(TaskService::class)->moveCardsAcrossLists($listsPayload, Auth::user(), $movedTaskId);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa memindahkan tugas ini')->danger()->send();
            unset($this->tasks);
            return;
        }

        foreach (array_keys($listsPayload) as $listId) {
            $list = TaskList::find($listId);
            if (! $list?->is_final) {
                continue;
            }

            foreach ($listsPayload[$listId] as $taskId) {
                if (($listBefore[$taskId] ?? $listId) === $listId) {
                    continue;
                }

                $this->dispatch('task-completed');

                $task = Task::find($taskId);
                if ($task && $task->requiresRatingFrom(Auth::id())) {
                    $this->openTaskDetail($taskId);
                }
            }
        }

        unset($this->tasks, $this->historyTasks);
    }

    public function openMembersModal(): void
    {
        $this->newProjectMemberId = null;
        $this->showMembersModal = true;
    }

    public function closeMembersModal(): void
    {
        $this->showMembersModal = false;
    }

    public function addProjectMember(): void
    {
        if (! $this->activeProject || ! $this->newProjectMemberId) {
            return;
        }

        try {
            app(TaskService::class)->addProjectMember($this->activeProject, $this->newProjectMemberId, Auth::user());
            $this->newProjectMemberId = null;
            unset($this->projectMembers, $this->companyUsers);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Gagal menambah anggota')->danger()->send();
        }
    }

    public function deleteProject(): void
    {
        if (! $this->activeProject) {
            return;
        }

        try {
            $result = app(TaskService::class)->removeProject($this->activeProject, Auth::user());
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menghapus Board ini')->danger()->send();

            return;
        }

        Notification::make()
            ->title($result === 'archived' ? 'Board diarsipkan - bisa dilihat lagi di Arsip' : 'Board dihapus')
            ->success()
            ->send();

        $this->redirectRoute('tugas', navigate: true);
    }

    public function restoreProject(): void
    {
        if (! $this->activeProject) {
            return;
        }

        try {
            app(TaskService::class)->restoreProject($this->activeProject, Auth::user());
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa memulihkan Board ini')->danger()->send();

            return;
        }

        Notification::make()->title('Board dipulihkan')->success()->send();
        $this->redirectRoute('tugas.papan.board', ['project' => $this->activeProjectId], navigate: true);
    }

    public function removeProjectMember(string $userId): void
    {
        if (! $this->activeProject) {
            return;
        }

        try {
            app(TaskService::class)->removeProjectMember($this->activeProject, $userId, Auth::user());
            unset($this->projectMembers, $this->companyUsers);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Gagal mengeluarkan anggota')->danger()->send();
        }
    }

    public function toggleAssigner(string $userId, bool $isAssigner): void
    {
        if (! $this->activeProject) {
            return;
        }

        try {
            app(TaskService::class)->setProjectMemberAssigner($this->activeProject, $userId, $isAssigner, Auth::user());
            unset($this->projectMembers);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Gagal mengubah izin assign')->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.task.task-list-page');
    }
}
