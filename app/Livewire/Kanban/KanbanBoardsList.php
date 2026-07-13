<?php

namespace App\Livewire\Kanban;

use App\Models\Kanban\KanbanBoard;
use App\Models\Kanban\KanbanTask;
use App\Models\Kanban\KanbanTaskMember;
use App\Services\Kanban\KanbanAuthorizationService;
use App\Services\OrgHierarchyService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class KanbanBoardsList extends Component
{
    // Modal "Kanban Staff" — supervisor kasih task tanpa board langsung ke satu bawahan.
    public bool $showStaffTaskModal = false;
    public ?string $staff_id = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $due_date = null;
    public string $priority = KanbanTask::PRIORITY_MEDIUM;

    public function getBoardsProperty()
    {
        return KanbanBoard::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', Auth::id()))
            ->withCount('tasks')
            ->with(['owner', 'members.user.profile'])
            ->latest()
            ->get();
    }

    public function getCanCreateBoardProperty(): bool
    {
        return app(KanbanAuthorizationService::class)->canCreateBoard(Auth::user());
    }

    // Bawahan langsung (rekursif) — reuse OrgHierarchyService yang sama seperti MyStaff.
    public function getSubordinatesProperty()
    {
        return app(OrgHierarchyService::class)->subordinates(Auth::user())->orderBy('name')->get(['id', 'name']);
    }

    // Task ditugaskan (dibuat lewat "Kanban Staff") yang melibatkan aku — sebagai atasan (created_by)
    // atau staff (assigned_to) — tampil langsung di /kanban, bukan cuma di BoardKu. Yang sudah
    // selesai hilang dari sini juga (sama seperti aturan di KanbanPersonalTasks).
    public function getStaffTasksProperty()
    {
        return KanbanTask::whereNull('board_id')
            ->whereColumn('created_by', '!=', 'assigned_to')
            ->where(function ($q) {
                $q->where('created_by', Auth::id())->orWhere('assigned_to', Auth::id());
            })
            ->where('status', '!=', KanbanTask::STATUS_DONE)
            ->with('assignedTo', 'createdBy')
            ->orderByDesc('created_at')
            ->get();
    }

    public function moveStaffTaskStatus(string $taskId, string $status): void
    {
        if (! in_array($status, KanbanTask::STATUSES, true)) {
            return;
        }

        $task = KanbanTask::whereNull('board_id')
            ->where(function ($q) {
                $q->where('created_by', Auth::id())->orWhere('assigned_to', Auth::id());
            })
            ->findOrFail($taskId);

        $task->update([
            'status' => $status,
            'completed_at' => $status === KanbanTask::STATUS_DONE ? now() : null,
        ]);
    }

    public function deleteStaffTask(string $taskId): void
    {
        $task = KanbanTask::where('created_by', Auth::id())
            ->whereNull('board_id')
            ->findOrFail($taskId);

        $task->delete();
        Notification::make()->title('Task dihapus')->success()->send();
    }

    public function openStaffTaskModal(): void
    {
        abort_unless($this->subordinates->isNotEmpty(), 403);

        $this->reset(['staff_id', 'title', 'description', 'due_date']);
        $this->priority = KanbanTask::PRIORITY_MEDIUM;
        $this->showStaffTaskModal = true;
    }

    public function closeStaffTaskModal(): void
    {
        $this->showStaffTaskModal = false;
    }

    public function createStaffTask(): void
    {
        $subordinateIds = $this->subordinates->pluck('id')->all();

        $data = $this->validate([
            'staff_id' => ['required', 'in:' . implode(',', $subordinateIds)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:' . implode(',', KanbanTask::PRIORITIES)],
        ]);

        $task = KanbanTask::create([
            'board_id' => null,
            'title' => $data['title'],
            'description' => $data['description'],
            'created_by' => Auth::id(),
            'assigned_to' => $data['staff_id'],
            'due_date' => $data['due_date'],
            'priority' => $data['priority'],
        ]);

        KanbanTaskMember::firstOrCreate([
            'task_id' => $task->id,
            'user_id' => $data['staff_id'],
        ]);

        $this->showStaffTaskModal = false;
        Notification::make()->title('Task berhasil ditugaskan ke staff')->success()->send();
    }

    public function render()
    {
        return view('livewire.kanban.kanban-boards-list')
            ->layout('components.layout', ['title' => 'Kanban']);
    }
}
