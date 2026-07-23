<?php

namespace App\Livewire\Task;

use App\Enum\Task\TaskPriority;
use App\Enum\Task\TaskStatus;
use App\Models\Task\Task;
use App\Models\Task\TaskChecklistItem;
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

    public string $scope = 'saya'; // saya | dibuat | tim | riwayat
    public string $view = 'board'; // board | list

    // Cuma dipakai kalau scope === 'riwayat' — section sendiri di sidebar (sejajar Project),
    // isinya 1 tombol per status, bukan tampilan gabungan seperti saya/dibuat/tim.
    public ?string $riwayatStatus = null;

    #[Url]
    public ?string $activeProjectId = null;

    // Modal "Tugas Baru"
    public bool $showTaskModal = false;
    public ?string $requirement = null;
    public ?string $due_date = null;
    public string $priority = 'medium';
    public ?string $assigned_to = null;
    public array $checklist = [];
    public bool $requiresApproval = false;
    public ?string $service_id = null;
    public array $reviewerIds = [];

    // Modal detail task
    public ?string $selectedTaskId = null;
    public string $newComment = '';
    public string $newChecklistTitle = '';
    public ?int $reviewRating = null;
    public ?string $reviewComment = null;
    public bool $showRejectForm = false;
    public string $rejectNote = '';

    // Modal Lampiran (terpisah dari modal detail)
    public ?string $attachmentsTaskId = null;
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

    public function mount(): void
    {
        $this->requiresApproval = app(TaskService::class)->requiresApprovalByDefault(Auth::user()->company_id);

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

    public function getTaskServicesProperty()
    {
        return app(TaskService::class)->getAllTaskServices();
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

    // Reviewer dipilih manual saat bikin task — pool-nya semua user 1 company (bukan
    // TaskService::getReviewers() lama, itu department-scoped buat kebutuhan mobile beda).
    public function getReviewerOptionsProperty()
    {
        return User::query()
            ->where('company_id', Auth::user()->company_id)
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

        if ($this->activeProject) {
            return $service->getProjectTasks($this->activeProject);
        }

        return $service->getPortalTasks(Auth::user(), $this->scope, $this->riwayatStatus)
            ->load(['assignedTo', 'createdBy', 'checklistItems', 'comments.user'])
            ->loadCount(['attachments', 'reviews']);
    }

    public function getTasksByStatusProperty(): array
    {
        $tasks = $this->tasks;

        return collect(TaskStatus::cases())
            ->mapWithKeys(fn (TaskStatus $status) => [$status->value => $tasks->where('status', $status->value)->values()])
            ->all();
    }

    public function selectScope(string $scope): void
    {
        $this->scope = $scope;
        $this->activeProjectId = null;
        $this->riwayatStatus = null;
        unset($this->tasks);
    }

    /** Riwayat itu section sendiri di sidebar (sejajar Project) — 1 tombol per status,
     * bukan 1 tombol gabungan kayak saya/dibuat/tim. */
    public function selectRiwayatStatus(string $status): void
    {
        $this->scope = 'riwayat';
        $this->riwayatStatus = $status;
        $this->activeProjectId = null;
        // Board 2 kolom (Selesai/Ditolak doang) kurang berguna buat riwayat 1 status — List
        // langsung kebaca lewat badge kolom Status yang sudah ada.
        $this->view = 'list';
        unset($this->tasks);
    }

    public function selectProject(string $projectId): void
    {
        $project = TaskProject::findOrFail($projectId);
        abort_unless(app(TaskService::class)->canAccessProject($project, Auth::user()), 403);

        $this->activeProjectId = $projectId;
        unset($this->tasks);
    }

    public function openCreateTask(): void
    {
        $this->reset(['requirement', 'due_date', 'assigned_to', 'checklist', 'service_id', 'newChecklistTitle', 'reviewerIds']);
        $this->priority = TaskPriority::MEDIUM->value;
        $this->requiresApproval = app(TaskService::class)->requiresApprovalByDefault(Auth::user()->company_id);
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

    protected function taskRules(): array
    {
        return [
            'requirement' => ['required', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:' . implode(',', array_column(TaskPriority::cases(), 'value'))],
            'assigned_to' => ['nullable', 'string'],
            'service_id' => [$this->requiresApproval ? 'required' : 'nullable', 'string'],
        ];
    }

    public function createTask(): void
    {
        $data = $this->validate($this->taskRules());
        $service = app(TaskService::class);

        if ($this->requiresApproval) {
            try {
                $service->createFormalTask([
                    'department_id' => Auth::user()->profile?->department?->id,
                    'service_id' => $data['service_id'],
                    'requirement' => $data['requirement'],
                    'priority' => $data['priority'],
                    'due_date' => $data['due_date'],
                    'checklist' => $this->checklist,
                    'reviewer_ids' => $this->reviewerIds,
                ]);

                Notification::make()->title('Tugas diajukan, menunggu approval')->success()->send();
            } catch (\Throwable $e) {
                Notification::make()->title($e->getMessage())->danger()->send();
                return;
            }
        } else {
            try {
                $service->createCasualTask([
                    'requirement' => $data['requirement'],
                    'priority' => $data['priority'],
                    'due_date' => $data['due_date'],
                    'assigned_to' => $data['assigned_to'] ?? null,
                    'project_id' => $this->activeProjectId,
                    'checklist' => $this->checklist,
                    'reviewer_ids' => $this->reviewerIds,
                ]);

                Notification::make()->title('Tugas berhasil dibuat')->success()->send();
            } catch (\Throwable $e) {
                Notification::make()->title($e->getMessage() ?: 'Tidak bisa membuat tugas ini')->danger()->send();
                return;
            }
        }

        $this->showTaskModal = false;
        unset($this->tasks);
    }

    public function moveTaskStatus(string $taskId, string $status): void
    {
        $task = Task::findOrFail($taskId);

        try {
            app(TaskService::class)->moveStatus($task, $status, Auth::user());
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa memindahkan tugas ini')->danger()->send();
        }

        unset($this->tasks);
    }

    public function openTaskDetail(string $taskId): void
    {
        $this->selectedTaskId = $taskId;
        $this->newComment = '';
        $this->showRejectForm = false;
        $this->rejectNote = '';
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

        return Task::with(['assignedTo', 'createdBy', 'checklistItems', 'comments.user', 'attachments', 'proofs', 'reviews.reviewer', 'rejectionNoteBy'])
            ->find($this->selectedTaskId);
    }

    /** Baris task_reviews milik user yang login, buat selectedTask — null kalau dia bukan reviewer. */
    public function getMyReviewRowProperty()
    {
        if (! $this->selectedTask) {
            return null;
        }

        return $this->selectedTask->reviews->firstWhere('reviewer_id', Auth::id());
    }

    public function submitReview(string $taskId): void
    {
        $data = $this->validate([
            'reviewRating' => ['required', 'integer', 'min:1', 'max:5'],
            'reviewComment' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(TaskService::class)->approveReview(Task::findOrFail($taskId), Auth::user(), $data['reviewRating'], $data['reviewComment']);
            $this->reset(['reviewRating', 'reviewComment']);
            unset($this->tasks, $this->selectedTask);
            Notification::make()->title('Review terkirim')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Gagal mengirim review')->danger()->send();
        }
    }

    public function openRejectForm(): void
    {
        $this->showRejectForm = true;
        $this->rejectNote = '';
    }

    public function cancelRejectForm(): void
    {
        $this->showRejectForm = false;
        $this->rejectNote = '';
    }

    public function submitReject(string $taskId): void
    {
        $data = $this->validate(['rejectNote' => ['required', 'string', 'max:1000']]);

        try {
            app(TaskService::class)->rejectReview(Task::findOrFail($taskId), Auth::user(), $data['rejectNote']);
            $this->reset(['showRejectForm', 'rejectNote']);
            unset($this->tasks, $this->selectedTask);
            Notification::make()->title('Tugas dikembalikan ke pelaksana')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Gagal menolak tinjauan')->danger()->send();
        }
    }

    public function toggleChecklistItem(string $itemId): void
    {
        $item = TaskChecklistItem::findOrFail($itemId);
        app(TaskService::class)->toggleChecklistItem($item);
    }

    public function addComment(string $taskId): void
    {
        if (trim($this->newComment) === '') {
            return;
        }

        app(TaskService::class)->addComment(Task::findOrFail($taskId), Auth::user(), $this->newComment);
        $this->newComment = '';
        unset($this->tasks);
    }

    public function openAttachments(string $taskId): void
    {
        $this->attachmentsTaskId = $taskId;
    }

    public function closeAttachments(): void
    {
        $this->attachmentsTaskId = null;
    }

    public function getAttachmentsTaskProperty(): ?Task
    {
        if (! $this->attachmentsTaskId) {
            return null;
        }

        return Task::with('attachments')->find($this->attachmentsTaskId);
    }

    public function uploadFileAttachment(): void
    {
        if (! $this->newAttachmentFile || ! $this->attachmentsTask) {
            return;
        }

        $this->validate(['newAttachmentFile' => ['file', 'max:10240']]);

        app(TaskService::class)->addFileAttachment($this->attachmentsTask, Auth::user(), $this->newAttachmentFile);
        $this->reset(['newAttachmentFile']);
        unset($this->attachmentsTask, $this->tasks);
    }

    public function addLinkAttachment(): void
    {
        if (trim($this->newAttachmentLink) === '' || ! $this->attachmentsTask) {
            return;
        }

        $this->validate(['newAttachmentLink' => ['url']]);

        app(TaskService::class)->addLinkAttachment($this->attachmentsTask, Auth::user(), $this->newAttachmentLink, $this->newAttachmentLinkLabel ?: null);
        $this->reset(['newAttachmentLink', 'newAttachmentLinkLabel']);
        unset($this->attachmentsTask, $this->tasks);
    }

    public function removeAttachment(string $attachmentId): void
    {
        app(TaskService::class)->removeAttachment($attachmentId);
        unset($this->attachmentsTask, $this->tasks);
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
