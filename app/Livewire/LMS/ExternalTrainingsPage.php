<?php

namespace App\Livewire\LMS;

use App\Enum\CustomPermissionEnum;
use App\Models\LMS\TrainingRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Pelatihan Eksternal" — daftar pengajuan pelatihan karyawan (training_requests) buat tim LMS,
 * dipisah dari alur approval atasan (portal /pelatihan/pengajuan-saya). Di sini tim LMS
 * menentukan honorarium (lihat ExternalTrainingDetailPage), approver tetap approve/reject di
 * halaman portal seperti biasa.
 */
#[Layout('components.layout', ['title' => 'Pelatihan Eksternal'])]
class ExternalTrainingsPage extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getRequestsProperty()
    {
        $search = trim($this->search);

        return TrainingRequests::query()
            ->with('user')
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->appends(['search' => $this->search, 'status' => $this->status]);
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
            'in_review', 'pending' => 'Menunggu Persetujuan',
            'rejected' => 'Ditolak',
            default => $status,
        };
    }

    public function render()
    {
        return view('livewire.lms.external-trainings-page');
    }
}
