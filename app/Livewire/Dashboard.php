<?php

namespace App\Livewire;

use App\Models\Kanban\KanbanTask;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Beranda'])]
class Dashboard extends Component
{
    public function getRecentKanbanTasksProperty()
    {
        $userId = Auth::id();

        return KanbanTask::with('board')
            ->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhere('assigned_to', $userId)
                    ->orWhereHas('members', fn ($q) => $q->where('user_id', $userId));
            })
            ->whereIn('status', [KanbanTask::STATUS_TODO, KanbanTask::STATUS_IN_PROGRESS, KanbanTask::STATUS_REVIEW])
            ->orderByDesc('created_at')
            ->limit(4)
            ->get();
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
