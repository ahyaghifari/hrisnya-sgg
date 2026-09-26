<?php

namespace App\Livewire\Assessment;

use App\Models\Assessment\Assessments;
use App\Models\Assessment\AssessmentUsers;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rekap terpusat semua AssessmentUsers lintas karyawan — biar HC bisa pantau progress
 * penilaian tanpa harus buka halaman karyawan satu-satu (itu sekarang cuma histori read-only,
 * lihat EmployeeStatistics tab Penilaian).
 */
#[Layout('components.layout', ['title' => 'Daftar Penilaian'])]
class DaftarPenilaianPage extends Component
{
    use WithPagination;

    #[Url]
    public string $jadwalId = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', AssessmentUsers::class), 403);
    }

    public function updatedJadwalId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getJadwalOptionsProperty()
    {
        return Assessments::orderByDesc('created_at')->pluck('name', 'id');
    }

    public function getRowsProperty()
    {
        return AssessmentUsers::query()
            ->whereHas('assessments')
            ->whereHas('target')
            ->with(['target', 'assessments'])
            ->when($this->jadwalId !== '', fn ($q) => $q->where('assessment_id', $this->jadwalId))
            ->when(trim($this->search) !== '', fn ($q) => $q->whereHas('target', fn ($t) => $t->where('name', 'like', '%' . trim($this->search) . '%')))
            ->latest()
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.assessment.daftar-penilaian-page');
    }
}
