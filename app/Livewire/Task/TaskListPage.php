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

/**
 * Halaman "Tugas" portal — pengganti modul Kanban. Board/List cuma mode tampilan atas
 * task_records yang sudah bisa dilihat user (assigned_to/created_by/bawahan/anggota project),
 * project sekarang entitas ringan (task_projects + task_project_members) — sidebar khusus
 * halaman ini, lihat planning/cached-dreaming-ladybug.md.
 */
#[Layout('components.layout', ['title' => 'Tugas'])]
class TaskListPage extends Component
{
    public string $scope = 'saya'; // saya | dibuat | tim
    public string $view = 'board'; // board | list

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

    // Modal detail task
    public ?string $selectedTaskId = null;
    public string $newComment = '';
    public string $newChecklistTitle = '';

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

        return $service->getPortalTasks(Auth::user(), $this->scope)
            ->load(['assignedTo', 'createdBy', 'checklistItems']);
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
        $this->reset(['requirement', 'due_date', 'assigned_to', 'checklist', 'service_id', 'newChecklistTitle']);
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

        return Task::with(['assignedTo', 'createdBy', 'checklistItems', 'comments.user', 'attachments', 'proofs'])
            ->find($this->selectedTaskId);
    }

    public function toggleChecklistItem(string $itemId): void
    {
        $item = TaskChecklistItem::findOrFail($itemId);
        app(TaskService::class)->toggleChecklistItem($item);
    }

    public function addComment(): void
    {
        if (trim($this->newComment) === '' || ! $this->selectedTask) {
            return;
        }

        app(TaskService::class)->addComment($this->selectedTask, Auth::user(), $this->newComment);
        $this->newComment = '';
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

    public function render()
    {
        return view('livewire.task.task-list-page');
    }
}
