<?php

namespace App\Livewire\Pelatihan;

use App\Models\LMS\TrainingRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layout', ['title' => 'Pengajuan Pelatihan'])]
class MyTrainingRequests extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        abort_if(Auth::user()->canBrowseAllCompanies(), 403);
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function getRequestsProperty()
    {
        return TrainingRequests::query()
            ->where('user_id', Auth::id())
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->appends(['status' => $this->status]);
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'approved' => 'badge-approved',
            'in_review', 'pending' => 'badge-pending',
            'rejected' => 'badge-rejected',
            default => 'badge-cancelled',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'approved' => 'Disetujui',
            'in_review' => 'Menunggu Persetujuan',
            'pending' => 'Menunggu Persetujuan',
            'rejected' => 'Ditolak',
            default => $status,
        };
    }

    public function render()
    {
        return view('livewire.pelatihan.my-training-requests');
    }
}
