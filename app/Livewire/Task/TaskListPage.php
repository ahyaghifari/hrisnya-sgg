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
    public string $view = 'board'; // board | list
    public string $search = ''; // filter board by kebutuhan/nama assignee

    #[Url]
    public ?string $activeProjectId = null;

    // Modal "Tugas Baru"
    public bool $showTaskModal = false;
    public ?string $requirement = null;
    public ?string $due_date = null;
    public string $priority = 'medium';
    public ?string $assigned_to = null;
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

    // Modal "Project Baru"
    public bool $showProjectModal = false;
    public string $projectName = '';
    public ?string $projectDescription = null;
    public string $projectColor = '#042c6c';
    public array $projectMemberIds = [];

    // Modal "Kelola Anggota"
    public bool $showMembersModal = false;
    public ?string $newProjectMemberId = null;

    // List custom Board - form tambah List baru + inline rename (dua-duanya owner-only, dicek
    // ulang di TaskService, ini cuma state form).
    public string $newListName = '';
    public string $newListColor = '#475569';
    public ?string $editingListId = null;
    public string $editingListName = '';

    // Modal "Ganti Background" board
    public bool $showBgModal = false;
    public string $bgType = 'color';
    public string $bgColor = '#0f172a';
    public $bgImage = null;

    public function mount(): void
    {
        if ($this->activeProjectId && ! app(TaskService::class)->canAccessProject(TaskProject::findOrFail($this->activeProjectId), Auth::user())) {
            $this->activeProjectId = null;
        }
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

        return $this->myProjects->firstWhere('id', $this->activeProjectId);
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

    /**
     * Kunci penyimpanan background board. Todo Saya/Tugas Saya/Penugasan Individu berbagi 1
     * background (bukan per-tab) - board pribadi vs project punya kustomisasi terpisah, tiap
     * project ULID-nya sendiri.
     */
    public function getBoardKeyProperty(): string
    {
        return $this->activeProjectId ?: 'personal';
    }

    public function getBoardBackgroundProperty()
    {
        return app(TaskService::class)->getBoardBackground(Auth::user(), $this->boardKey);
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
            return null;
        }

        return app(TaskService::class)->getHistoryTasks(Auth::user(), $this->scope);
    }

    public function selectScope(string $scope): void
    {
        $this->scope = $scope;
        $this->activeProjectId = null;
        $this->resetPage();
        unset($this->tasks);
    }

    public function selectProject(string $projectId): void
    {
        // String kosong = pilihan placeholder "Pilih Project" di dropdown - balik ke tab biasa.
        if ($projectId === '') {
            $this->selectScope($this->scope);
            return;
        }

        $project = TaskProject::findOrFail($projectId);
        abort_unless(app(TaskService::class)->canAccessProject($project, Auth::user()), 403);

        $this->activeProjectId = $projectId;
        unset($this->tasks);
    }

    public function openCreateTask(): void
    {
        $this->reset(['requirement', 'due_date', 'assigned_to', 'checklist', 'newChecklistTitle', 'reviewerId']);
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
                'assigned_to' => $data['assigned_to'] ?? null,
                'project_id' => $this->activeProjectId,
                'reviewer_id' => $this->reviewerId,
                'needs_review' => $this->needsReview,
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

        try {
            app(TaskService::class)->moveStatus($task, $status, Auth::user());

            // Baru selesai & belum dirating - buka modal detail otomatis buat yang berhak rating
            // (reviewer kalau dipilih, fallback ke creator) supaya langsung bisa kasih rating
            // (opsional, tetap bisa ditutup tanpa rating).
            $allowedRaterId = $task->reviewer_id ?? $task->created_by;
            if ($status === TaskStatus::COMPLETE->value && $allowedRaterId === Auth::id() && ! $task->rating) {
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
        $this->selectedTaskId = null;
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
        if (! $this->newAttachmentFile || ! $this->selectedTask) {
            return;
        }

        $this->validate(['newAttachmentFile' => ['file', 'max:10240']]);

        app(TaskService::class)->addFileAttachment($this->selectedTask, Auth::user(), $this->newAttachmentFile);
        $this->reset(['newAttachmentFile']);
        unset($this->selectedTask, $this->tasks);
    }

    public function addLinkAttachment(): void
    {
        if (trim($this->newAttachmentLink) === '' || ! $this->selectedTask) {
            return;
        }

        $this->validate(['newAttachmentLink' => ['url']]);

        app(TaskService::class)->addLinkAttachment($this->selectedTask, Auth::user(), $this->newAttachmentLink, $this->newAttachmentLinkLabel ?: null);
        $this->reset(['newAttachmentLink', 'newAttachmentLinkLabel']);
        unset($this->selectedTask, $this->tasks);
    }

    public function removeAttachment(string $attachmentId): void
    {
        app(TaskService::class)->removeAttachment($attachmentId);
        unset($this->selectedTask, $this->tasks);
    }

    public function openCreateProjectModal(): void
    {
        $this->reset(['projectName', 'projectDescription', 'projectMemberIds']);
        $this->projectColor = '#042c6c';
        $this->showProjectModal = true;
    }

    public function closeProjectModal(): void
    {
        $this->showProjectModal = false;
    }

    public function createProject(): void
    {
        $data = $this->validate([
            'projectName' => ['required', 'string', 'max:255'],
            'projectDescription' => ['nullable', 'string'],
            'projectColor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $project = app(TaskService::class)->createProject([
            'name' => $data['projectName'],
            'description' => $data['projectDescription'],
            'color' => $data['projectColor'],
            'member_ids' => $this->projectMemberIds,
        ]);

        $this->showProjectModal = false;
        $this->activeProjectId = $project->id;
        unset($this->myProjects, $this->tasks);

        Notification::make()->title('Project berhasil dibuat')->success()->send();
    }

    public function createList(): void
    {
        if (! $this->activeProject || trim($this->newListName) === '') {
            return;
        }

        try {
            app(TaskService::class)->createList($this->activeProject, trim($this->newListName), Auth::user(), $this->newListColor);
            $this->newListName = '';
            $this->newListColor = '#475569';
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
    public function moveCardsAcrossLists(array $listsPayload): void
    {
        try {
            app(TaskService::class)->moveCardsAcrossLists($listsPayload, Auth::user());
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
                $task = Task::find($taskId);
                if ($task && ($task->reviewer_id ?? $task->created_by) === Auth::id() && ! $task->rating) {
                    $this->openTaskDetail($taskId);
                }
            }
        }

        unset($this->tasks, $this->historyTasks);
    }

    public function openBgModal(): void
    {
        $current = $this->boardBackground;
        $this->bgType = $current?->type ?? 'color';
        $this->bgColor = $current?->type === 'color' ? $current->value : '#0f172a';
        $this->bgImage = null;
        $this->showBgModal = true;
    }

    public function closeBgModal(): void
    {
        $this->showBgModal = false;
    }

    public function saveBoardBackground(): void
    {
        $service = app(TaskService::class);

        if ($this->bgType === 'image') {
            $this->validate(['bgImage' => ['required', 'image', 'max:4096']]);
            $service->setBoardBackgroundImage(Auth::user(), $this->boardKey, $this->bgImage);
        } else {
            $this->validate(['bgColor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]);
            $service->setBoardBackgroundColor(Auth::user(), $this->boardKey, $this->bgColor);
        }

        unset($this->boardBackground);
        $this->showBgModal = false;
        Notification::make()->title('Background board diperbarui')->success()->send();
    }

    public function removeBoardBackground(): void
    {
        app(TaskService::class)->removeBoardBackground(Auth::user(), $this->boardKey);
        unset($this->boardBackground);
        $this->showBgModal = false;
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
            app(TaskService::class)->deleteProject($this->activeProject, Auth::user());
            $this->activeProjectId = null;
            unset($this->myProjects, $this->tasks);
            Notification::make()->title('Board dihapus')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa menghapus Board ini')->danger()->send();
        }
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
