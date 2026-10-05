<?php

namespace App\Livewire\LMS;

use App\Enum\CustomPermissionEnum;
use App\Models\LMS\TrainingClaim;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar semua pengajuan Pelatihan Terlaksana buat HC/Kepegawaian. Pemeriksaan & approve
 * berjalan di alur persetujuan (modul training_claim) lewat TrainingClaimDetail, di sini cuma
 * monitor + pintu masuk ke detail.
 */
#[Layout('components.layout', ['title' => 'Pelatihan Terlaksana'])]
class TrainingClaimsPage extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'pending';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::KELOLA_KLAIM_PELATIHAN->value), 403);
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function getClaimsProperty()
    {
        return TrainingClaim::query()
            ->with('user')
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.lms.training-claims-page');
    }
}
