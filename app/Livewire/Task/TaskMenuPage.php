<?php

namespace App\Livewire\Task;

use App\Models\User;
use App\Services\OrgHierarchyService;
use App\Services\Task\TaskService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Tugas" (/tugas) - permukaan/menu, bukan papan. Tiap tile navigasi ke URL sendiri
 * (/tugas/papan/{scope} atau /tugas/papan/board/{project}) yang di-render TaskListPage - logic
 * board/list/modal task TIDAK diduplikasi di sini (lihat TaskListPage). Redesign 2026-09-19,
 * gantiin tab switcher inline yang tadinya nempel di TaskListPage.
 */
#[Layout('components.layout', ['title' => 'Tugas'])]
class TaskMenuPage extends Component
{
    // Modal "Board Baru" - dipindah dari TaskListPage (2026-09-19), board sekarang punya URL
    // sendiri jadi wajar dibuat dari halaman menu (tempat board di-listing), bukan dari papan.
    public bool $showProjectModal = false;
    public string $projectName = '';
    public ?string $projectDescription = null;
    public string $projectColor = '#042c6c';
    public array $projectMemberIds = [];
    // Perusahaan sumber calon anggota awal - null = perusahaan sendiri (default).
    public ?string $projectCompanyId = null;

    public function getHasSubordinatesProperty(): bool
    {
        return app(OrgHierarchyService::class)->subordinates(Auth::user())->exists();
    }

    public function getMyTaskPendingCountProperty(): int
    {
        return app(TaskService::class)->countIncomplete(Auth::user(), 'saya');
    }

    /**
     * Semua board yang diikuti user, satu daftar - tidak dipisah personal/tim lagi (dihapus
     * 2026-09-19, instruksi user: board solo-anggota bukan kategori terpisah, langsung gabung
     * ke daftar Board biasa).
     */
    public function getMyProjectsProperty()
    {
        return app(TaskService::class)->getMyProjects(Auth::user());
    }

    public function getCompanyUsersProperty()
    {
        // Calon anggota dari perusahaan terpilih (default: perusahaan sendiri) + orang yang sudah
        // dicentang dari perusahaan lain, supaya pilihan tidak hilang saat ganti perusahaan.
        return User::query()
            ->where(function ($q) {
                $q->where('company_id', $this->projectCompanyId ?: Auth::user()->company_id)
                    ->orWhereIn('id', $this->projectMemberIds);
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getCompaniesProperty()
    {
        return \App\Models\Master\Companies::orderBy('name')->get(['id', 'name']);
    }

    public function updatedProjectCompanyId($value): void
    {
        $this->projectCompanyId = filled($value) && \App\Models\Master\Companies::whereKey($value)->exists() ? $value : null;
    }

    public function openCreateProjectModal(): void
    {
        abort_unless(Auth::user()->can(\App\Enum\CustomPermissionEnum::BUAT_BOARD_TUGAS->value), 403);

        $this->reset(['projectName', 'projectDescription', 'projectMemberIds', 'projectCompanyId']);
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

        Notification::make()->title('Board berhasil dibuat')->success()->send();

        $this->redirectRoute('tugas.papan.board', ['project' => $project->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.task.task-menu-page');
    }
}
