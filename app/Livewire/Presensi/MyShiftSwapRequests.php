<?php

namespace App\Livewire\Presensi;

use App\Models\Shift\ShiftSwapRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Riwayat pengajuan tukar shift MILIK SENDIRI (requester_id) - pola sama persis
 * App\Livewire\MyAttendanceRequests (satu computed property dipakai bareng mobile card &
 * desktop table). Bukan tempat AJUKAN baru (itu modal di Dashboard, lihat
 * App\Livewire\Presensi\ShiftSwapRequestModal) - halaman ini murni riwayat + link ke detail.
 */
#[Layout('components.layout', ['title' => 'Tukar Shift'])]
class MyShiftSwapRequests extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    /** @return array<string,string> */
    public function getStatusOptionsProperty(): array
    {
        return [
            'in_review' => 'Diproses',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ];
    }

    public function getRequestsProperty()
    {
        return ShiftSwapRequests::query()
            ->where('requester_id', Auth::id())
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->with(['target', 'reqSchedule.shiftType', 'targetSchedule.shiftType'])
            ->orderByDesc('created_at')
            ->paginate(10)
            ->appends(['status' => $this->status]);
    }

    public function keterangan(ShiftSwapRequests $record): string
    {
        $req = $record->reqSchedule?->shiftType?->name . ' (' . $record->req_date->translatedFormat('d M') . ')';

        if ($record->targetSchedule) {
            return $req . ' ↔ ' . $record->targetSchedule->shiftType?->name . ' (' . $record->target_date->translatedFormat('d M') . ')';
        }

        return $req . ' → diserahkan ke ' . ($record->target?->name ?? '-');
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'in_review', 'pending' => 'Diproses',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => $status,
        };
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

    public function render()
    {
        return view('livewire.presensi.my-shift-swap-requests');
    }
}
