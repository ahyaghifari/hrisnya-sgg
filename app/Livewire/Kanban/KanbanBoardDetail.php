<?php

namespace App\Livewire\Kanban;

use App\Models\Kanban\KanbanBoard;
use App\Models\Kanban\KanbanBoardMember;
use App\Models\Kanban\KanbanTask;
use App\Models\Kanban\KanbanTaskAttachment;
use App\Models\Kanban\KanbanTaskComment;
use App\Models\Kanban\KanbanTaskItem;
use App\Models\Kanban\KanbanTaskMember;
use App\Models\User;
use App\Services\Kanban\KanbanAuthorizationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
    public array $assignedUsers = [];

    // Modal detail task
    public ?string $selectedTaskId = null;
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

    public function getCanCreateTaskProperty(): bool
    {
        return $this->authService()->canCreateTask($this->board, Auth::user());
    }

    public function getCanAddMemberProperty(): bool
    {
        return $this->authService()->canAddMember($this->board, Auth::user());
    }

    public function updateColor(string $color): void
    {
        abort_unless($this->canAddMember, 403); // owner-only, sama seperti kelola member

        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return;
        }

        $this->board->update(['color' => $color]);
    }

    public function getMembersProperty()
    {
        return $this->board->members()->with('user')->get();
    }

    // Orang yang boleh ditugaskan ke task = member board lain (bukan diri sendiri).
    public function getAssignableMembersProperty()
    {
        return $this->members->where('user_id', '!=', Auth::id());
    }

    public function getCompanyUsersProperty()
    {
        return User::query()
            ->where('company_id', $this->board->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function canChangeStatusFreely(KanbanTask $task): bool
    {
        return $this->authService()->canChangeStatusFreely($task, Auth::user());
    }

    public function getTasksByStatusProperty(): array
    {
        $tasks = $this->board->tasks()
            ->with(['assignedTo', 'members.user', 'items', 'comments.user'])
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();

        // Task yang kita jadi membernya naik ke atas per kolom; sortByDesc stabil (PHP 8+)
        // jadi urutan position/created_at di atas tetap terjaga di dalam masing-masing grup.
        $userId = Auth::id();
        $tasks = $tasks->sortByDesc(fn (KanbanTask $task) => $task->members->contains('user_id', $userId))->values();

        return collect(KanbanTask::STATUSES)
            ->mapWithKeys(fn ($status) => [$status => $tasks->where('status', $status)->values()])
            ->all();
    }

    public function getSelectedTaskProperty(): ?KanbanTask
    {
        if (! $this->selectedTaskId) {
            return null;
        }

        return KanbanTask::with(['assignedTo', 'createdBy', 'members.user', 'attachments.uploadedBy'])
            ->find($this->selectedTaskId);
    }

    public function openCreateTask(): void
    {
        abort_unless($this->canCreateTask, 403);

        $this->reset(['title', 'description', 'due_date', 'assignedUsers']);
        $this->priority = KanbanTask::PRIORITY_MEDIUM;
        $this->showTaskModal = true;
    }

    public function closeTaskModal(): void
    {
        $this->showTaskModal = false;
    }

    protected function taskRules(): array
    {
        $assignableIds = $this->assignableMembers->pluck('user_id')->all();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:' . implode(',', KanbanTask::PRIORITIES)],
            'assignedUsers' => ['required', 'array', 'min:1'],
            'assignedUsers.*' => [Rule::in($assignableIds)],
        ];
    }

    public function createTask(): void
    {
        abort_unless($this->canCreateTask, 403);

        $data = $this->validate($this->taskRules());
        $user = Auth::user();

        DB::transaction(function () use ($data, $user) {
            $task = KanbanTask::create([
                'board_id' => $this->board->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'created_by' => $user->id,
                'assigned_to' => $data['assignedUsers'][0],
                'due_date' => $data['due_date'],
                'priority' => $data['priority'],
            ]);

            foreach ($data['assignedUsers'] as $userId) {
                KanbanTaskMember::firstOrCreate([
                    'task_id' => $task->id,
                    'user_id' => $userId,
                ]);
            }
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

        if (! $this->authService()->canMoveStatus($task, Auth::user(), $status)) {
            if ($task->status !== KanbanTask::STATUS_DONE) {
                Notification::make()->title('Kamu cuma boleh memajukan status 1 langkah')->danger()->send();
            }
            return; // task selesai terkunci — tidak bisa diubah siapapun, cuma bisa dikomentari
        }

        $task->update([
            'status' => $status,
            'completed_at' => $status === KanbanTask::STATUS_DONE ? now() : null,
        ]);
    }

    public function openTask(string $taskId): void
    {
        $this->selectedTaskId = $taskId;
        $this->newAttachment = null;
    }

    public function closeTask(): void
    {
        $this->selectedTaskId = null;
    }

    public function addCommentToTask(string $taskId, string $comment): void
    {
        $this->board->tasks()->findOrFail($taskId);

        $comment = trim($comment);

        if ($comment === '') {
            return;
        }

        KanbanTaskComment::create([
            'task_id' => $taskId,
            'user_id' => Auth::id(),
            'comment' => $comment,
        ]);
    }

    public function canManageItems(KanbanTask $task): bool
    {
        return $this->authService()->canManageItems($task, Auth::user());
    }

    public function addItemToTask(string $taskId, string $title): void
    {
        $task = $this->board->tasks()->findOrFail($taskId);

        abort_unless($this->canManageItems($task), 403);

        if ($task->status === KanbanTask::STATUS_DONE) {
            return; // task selesai terkunci
        }

        $title = trim($title);

        if ($title === '' || mb_strlen($title) > 255) {
            return;
        }

        KanbanTaskItem::create([
            'task_id' => $taskId,
            'title' => $title,
        ]);
    }

    public function toggleItem(string $itemId): void
    {
        $item = KanbanTaskItem::whereIn('task_id', $this->board->tasks()->pluck('id'))->with('task')->findOrFail($itemId);

        if ($item->task->status === KanbanTask::STATUS_DONE) {
            return; // task selesai terkunci
        }

        $item->update(['is_done' => ! $item->is_done]);
    }

    public function deleteItem(string $itemId): void
    {
        $item = KanbanTaskItem::whereIn('task_id', $this->board->tasks()->pluck('id'))->with('task')->findOrFail($itemId);

        if ($item->task->status === KanbanTask::STATUS_DONE) {
            return; // task selesai terkunci
        }

        $item->delete();
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
