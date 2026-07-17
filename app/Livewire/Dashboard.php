<?php

namespace App\Livewire;

use App\Models\Kanban\KanbanTask;
use App\Models\LMS\LmsCourseUsers;
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

    // Pelatihan yang sedang dijalani (in_progress) atau sudah jadi member tapi belum mulai
    // (requested/approved) — completed/canceled sengaja dikecualikan, itu bukan "saat ini".
    public function getMyTrainingsProperty()
    {
        return LmsCourseUsers::with('course.category')
            ->where('user_id', Auth::id())
            ->whereIn('status', ['requested', 'approved', 'in_progress'])
            ->orderByDesc('enrolled_at')
            ->limit(4)
            ->get();
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
