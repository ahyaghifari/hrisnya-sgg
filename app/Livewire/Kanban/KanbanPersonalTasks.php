<?php

namespace App\Livewire\Kanban;

use App\Models\Kanban\KanbanTask;
use App\Models\Kanban\KanbanTaskMember;
use App\Services\Kanban\KanbanAuthorizationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class KanbanPersonalTasks extends Component
{
    public bool $showTaskModal = false;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $due_date = null;
    public string $priority = KanbanTask::PRIORITY_MEDIUM;

    public function getTasksByStatusProperty(): array
    {
        // BoardKu cuma nampilin task yang ditugaskan KE aku (assigned_to), bukan yang aku
        // titipkan ke staff — task yang aku kasih ke bawahan cuma muncul di BoardKu bawahan
        // itu sendiri (dan di section "Kanban Staff" /kanban untuk aksi cepat sisi atasan).
        $tasks = KanbanTask::whereNull('board_id')
            ->with('assignedTo', 'createdBy')
            ->where('assigned_to', Auth::id())
            // Task ditugaskan (created_by != assigned_to) yang sudah selesai hilang dari board —
            // task pribadi sendiri (created_by == assigned_to) tetap kelihatan pas selesai.
            ->where(function ($q) {
                $q->where('status', '!=', KanbanTask::STATUS_DONE)
                    ->orWhereColumn('created_by', 'assigned_to');
            })
            ->orderByDesc('created_at')
            ->get();

        return collect(KanbanTask::STATUSES)
            ->mapWithKeys(fn ($status) => [$status => $tasks->where('status', $status)->values()])
            ->all();
    }

    public function openCreateTask(): void
    {
        $this->reset(['title', 'description', 'due_date']);
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
        ];
    }

    public function createTask(): void
    {
        $data = $this->validate($this->taskRules());
        $user = Auth::user();

        $task = KanbanTask::create([
            'board_id' => null,
            'title' => $data['title'],
            'description' => $data['description'],
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'due_date' => $data['due_date'],
            'priority' => $data['priority'],
        ]);

        KanbanTaskMember::firstOrCreate([
            'task_id' => $task->id,
            'user_id' => $user->id,
        ]);

        $this->showTaskModal = false;
        Notification::make()->title('Task pribadi berhasil dibuat')->success()->send();
    }

    public function moveTaskStatus(string $taskId, string $status): void
    {
        if (! in_array($status, KanbanTask::STATUSES, true)) {
            return;
        }

        $task = KanbanTask::whereNull('board_id')
            ->where(function ($q) {
                $q->where('created_by', Auth::id())->orWhere('assigned_to', Auth::id());
            })
            ->findOrFail($taskId);

        if (! app(KanbanAuthorizationService::class)->canMoveStatus($task, Auth::user(), $status)) {
            Notification::make()->title('Kamu cuma boleh memajukan status 1 langkah')->danger()->send();
            return;
        }

        $task->update([
            'status' => $status,
            'completed_at' => $status === KanbanTask::STATUS_DONE ? now() : null,
        ]);
    }

    public function deleteTask(string $taskId): void
    {
        $task = KanbanTask::where('created_by', Auth::id())
            ->whereNull('board_id')
            ->findOrFail($taskId);

        $task->delete();
        Notification::make()->title('Task dihapus')->success()->send();
    }

    public function render()
    {
        return view('livewire.kanban.kanban-personal-tasks')
            ->layout('components.layout', ['title' => 'Tugas Pribadi']);
    }
}
