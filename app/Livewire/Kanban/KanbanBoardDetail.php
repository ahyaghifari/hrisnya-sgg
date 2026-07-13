<?php

namespace App\Livewire\Kanban;

use App\Models\Kanban\KanbanBoard;
use App\Models\Kanban\KanbanBoardMember;
use App\Models\Kanban\KanbanTask;
use App\Models\Kanban\KanbanTaskAttachment;
use App\Models\Kanban\KanbanTaskComment;
use App\Models\Kanban\KanbanTaskMember;
use App\Models\User;
use App\Services\Kanban\KanbanAuthorizationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class KanbanBoardDetail extends Component
{
    use WithFileUploads;

    public KanbanBoard $board;

    // Modal "Task Baru"
    public bool $showTaskModal = false;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $due_date = null;
    public string $priority = KanbanTask::PRIORITY_MEDIUM;
    public ?string $assigned_to = null;

    // Modal detail task
    public ?string $selectedTaskId = null;
    public ?string $newComment = null;
    #[Validate('nullable|image|max:5120')]
    public $newAttachment = null;

    // Modal member
    public bool $showMemberModal = false;
    public ?string $newMemberUserId = null;

    public function mount(KanbanBoard $board): void
    {
        abort_unless(app(KanbanAuthorizationService::class)->canAccessBoard($board, Auth::user()), 403);

        $this->board = $board;
    }

    protected function authService(): KanbanAuthorizationService
    {
        return app(KanbanAuthorizationService::class);
    }

    public function getCanAssignProperty(): bool
    {
        return $this->authService()->canAssignToOthers($this->board, Auth::user());
    }

    public function getCanAddMemberProperty(): bool
    {
        return $this->authService()->canAddMember($this->board, Auth::user());
    }

    public function getMembersProperty()
    {
        return $this->board->members()->with('user')->get();
    }

    public function getCompanyUsersProperty()
    {
        return User::query()
            ->where('company_id', $this->board->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getTasksByStatusProperty(): array
    {
        $tasks = $this->board->tasks()
            ->with(['assignedTo', 'members.user'])
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();

        return collect(KanbanTask::STATUSES)
            ->mapWithKeys(fn ($status) => [$status => $tasks->where('status', $status)->values()])
            ->all();
    }

    public function getSelectedTaskProperty(): ?KanbanTask
    {
        if (! $this->selectedTaskId) {
            return null;
        }

        return KanbanTask::with(['assignedTo', 'createdBy', 'members.user', 'comments.user', 'attachments.uploadedBy'])
            ->find($this->selectedTaskId);
    }

    public function openCreateTask(): void
    {
        $this->reset(['title', 'description', 'due_date', 'assigned_to']);
        $this->priority = KanbanTask::PRIORITY_MEDIUM;
        $this->showTaskModal = true;
    }

    public function closeTaskModal(): void
    {
        $this->showTaskModal = false;
    }

    protected function taskRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:' . implode(',', KanbanTask::PRIORITIES)],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }

    public function createTask(): void
    {
        $data = $this->validate($this->taskRules());
        $user = Auth::user();

        $assignedTo = $data['assigned_to'] ?: $user->id;

        if ($assignedTo !== $user->id && ! $this->canAssign) {
            Notification::make()->title('Kamu tidak punya izin assign task ke orang lain')->danger()->send();
            return;
        }

        DB::transaction(function () use ($data, $user, $assignedTo) {
            $task = KanbanTask::create([
                'board_id' => $this->board->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'created_by' => $user->id,
                'assigned_to' => $assignedTo,
                'due_date' => $data['due_date'],
                'priority' => $data['priority'],
            ]);

            KanbanTaskMember::firstOrCreate([
                'task_id' => $task->id,
                'user_id' => $assignedTo,
            ]);
        });

        $this->showTaskModal = false;
        Notification::make()->title('Task berhasil dibuat')->success()->send();
    }

    public function moveTaskStatus(string $taskId, string $status): void
    {
        if (! in_array($status, KanbanTask::STATUSES, true)) {
            return;
        }

        $task = $this->board->tasks()->findOrFail($taskId);
        $task->update([
            'status' => $status,
            'completed_at' => $status === KanbanTask::STATUS_DONE ? now() : null,
        ]);
    }

    public function openTask(string $taskId): void
    {
        $this->selectedTaskId = $taskId;
        $this->newComment = null;
        $this->newAttachment = null;
    }

    public function closeTask(): void
    {
        $this->selectedTaskId = null;
    }

    public function addComment(): void
    {
        $this->validate(['newComment' => ['required', 'string']]);

        KanbanTaskComment::create([
            'task_id' => $this->selectedTaskId,
            'user_id' => Auth::id(),
            'comment' => $this->newComment,
        ]);

        $this->newComment = null;
    }

    public function uploadAttachment(): void
    {
        $this->validate(['newAttachment' => ['required', 'image', 'max:5120']]);

        $path = $this->newAttachment->store("kanban/{$this->selectedTaskId}/attachments", 'public');

        KanbanTaskAttachment::create([
            'task_id' => $this->selectedTaskId,
            'uploaded_by' => Auth::id(),
            'file_path' => $path,
            'file_name' => $this->newAttachment->getClientOriginalName(),
        ]);

        $this->newAttachment = null;
    }

    public function deleteAttachment(string $attachmentId): void
    {
        $attachment = KanbanTaskAttachment::findOrFail($attachmentId);

        if ($attachment->task->board_id !== $this->board->id) {
            abort(403);
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();
    }

    public function deleteTask(string $taskId): void
    {
        $task = $this->board->tasks()->findOrFail($taskId);

        abort_unless($this->authService()->canDeleteTask($task, Auth::user()), 403);

        $task->delete();

        if ($this->selectedTaskId === $taskId) {
            $this->selectedTaskId = null;
        }

        Notification::make()->title('Task dihapus')->success()->send();
    }

    public function openMemberModal(): void
    {
        $this->newMemberUserId = null;
        $this->showMemberModal = true;
    }

    public function addMember(): void
    {
        abort_unless($this->canAddMember, 403);

        $this->validate(['newMemberUserId' => ['required', 'exists:users,id']]);

        $user = User::findOrFail($this->newMemberUserId);
        abort_unless($user->company_id === $this->board->company_id, 403);

        KanbanBoardMember::firstOrCreate([
            'board_id' => $this->board->id,
            'user_id' => $user->id,
        ], [
            'role' => KanbanBoardMember::ROLE_MEMBER,
        ]);

        $this->newMemberUserId = null;
        Notification::make()->title('Member ditambahkan')->success()->send();
    }

    public function promoteToAssigner(string $memberId): void
    {
        abort_unless($this->canAddMember, 403); // owner-only, sama seperti canAddMember

        $member = $this->board->members()->findOrFail($memberId);

        if ($member->role === KanbanBoardMember::ROLE_OWNER) {
            return;
        }

        $member->update(['role' => KanbanBoardMember::ROLE_ASSIGNER]);
        Notification::make()->title('Member dinaikkan jadi Assigner')->success()->send();
    }

    public function removeMember(string $memberId): void
    {
        abort_unless($this->canAddMember, 403);

        $member = $this->board->members()->findOrFail($memberId);

        if ($member->role === KanbanBoardMember::ROLE_OWNER) {
            return;
        }

        $member->delete();
        Notification::make()->title('Member dikeluarkan')->success()->send();
    }

    public function render()
    {
        return view('livewire.kanban.kanban-board-detail')
            ->layout('components.layout', ['title' => $this->board->name]);
    }
}
