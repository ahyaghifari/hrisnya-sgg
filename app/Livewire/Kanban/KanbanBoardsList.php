<?php

namespace App\Livewire\Kanban;

use App\Models\Kanban\KanbanBoard;
use App\Services\Kanban\KanbanAuthorizationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class KanbanBoardsList extends Component
{
    public function getBoardsProperty()
    {
        return KanbanBoard::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', Auth::id()))
            ->withCount('tasks')
            ->with('owner')
            ->latest()
            ->get();
    }

    public function getCanCreateBoardProperty(): bool
    {
        return app(KanbanAuthorizationService::class)->canCreateBoard(Auth::user());
    }

    public function render()
    {
        return view('livewire.kanban.kanban-boards-list')
            ->layout('components.layout', ['title' => 'Kanban']);
    }
}
