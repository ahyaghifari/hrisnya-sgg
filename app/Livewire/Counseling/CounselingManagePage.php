<?php

namespace App\Livewire\Counseling;

use App\Enum\CounselingStatus;
use App\Models\Counseling\Counseling;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Counseling" (sisi konselor) — list keluhan yang DITUJUKAN ke karyawan yang login sebagai
 * konselor (`counselor_id = auth()->id()`), BUKAN lagi company-wide (dulu gerbang permission
 * Shield `ViewAny:Counseling`, sebelum ada penunjukan konselor per karyawan). Isi keluhan
 * rahasia — sengaja TIDAK ada filter company/lihat-semua di sini, termasuk Super Admin cuma
 * lihat punya sendiri sebagai konselor (lihat Counseling::isVisibleTo(), instruksi user
 * 2026-07-26). Search cuma di nama karyawan, bukan isi keluhan (issue tidak boleh ke-index
 * bebas walau cuma buat search sendiri, biar tidak ada celah kebiasaan search luas dulu).
 */
#[Layout('components.layout', ['title' => 'Counseling'])]
class CounselingManagePage extends Component
{
    use WithPagination;

    public string $status = '';
    public string $search = '';

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getStatusesProperty(): array
    {
        return CounselingStatus::cases();
    }

    protected function baseQuery()
    {
        $search = trim($this->search);

        return Counseling::query()
            ->where('counselor_id', Auth::id())
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->with('user');
    }

    public function getCounselingsProperty()
    {
        return $this->baseQuery()->latest()->paginate(10);
    }

    public function render()
    {
        return view('livewire.counseling.counseling-manage-page');
    }
}
