<?php

namespace App\Livewire\Task;

use App\Models\Task\TaskProject;
use App\Models\Task\TaskProjectMember;
use App\Services\Task\TaskService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Arsip Board (/tugas/arsip) - Board yang sudah punya tugas saat "dihapus" masuk sini, bisa dibuka (read-only) dan dipulihkan owner. */
#[Layout('components.layout', ['title' => 'Arsip Board'])]
class TaskArchivePage extends Component
{
    public function getArchivedProjectsProperty()
    {
        return app(TaskService::class)->getArchivedProjects(Auth::user());
    }

    public function getOwnedProjectIdsProperty(): array
    {
        return TaskProjectMember::where('user_id', Auth::id())
            ->where('role', TaskProjectMember::ROLE_OWNER)
            ->pluck('project_id')
            ->all();
    }

    public function restore(string $projectId): void
    {
        try {
            app(TaskService::class)->restoreProject(TaskProject::findOrFail($projectId), Auth::user());
            Notification::make()->title('Board dipulihkan')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage() ?: 'Tidak bisa memulihkan Board ini')->danger()->send();
        }

        unset($this->archivedProjects);
    }

    public function render()
    {
        return view('livewire.task.task-archive-page');
    }
}
