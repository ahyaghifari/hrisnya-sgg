<?php

namespace App\Livewire\Presensi;

use App\Livewire\Presensi\Concerns\FormatsShiftSwapRequest;
use App\Models\Shift\ShiftSwapRequests;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Riwayat tukar shift 1 JADWAL (bukan milik sendiri) - dibuka lewat ikon "tukar" emas di widget
 * "Jadwal Shift Tim" (Dashboard) & tab Penjadwalan halaman Detail Jadwal. Read-only, 30 baris
 * terbaru, semua status. Beda dari ShiftSwapHistorySheet yang scope-nya requester_id = user login.
 * Scope by reqSchedule->shiftType->schedule_id: 1 pengajuan tukar shift selalu antar rekan
 * sejadwal (target candidates di modal sudah difilter ke schedule yang sama), jadi cukup lihat
 * sisi pemohon.
 */
class ScheduleSwapHistorySheet extends Component
{
    use FormatsShiftSwapRequest;

    public ?string $scheduleId = null;

    public bool $showModal = false;

    /** Dibuka juga dari ikon "tukar" emas yang nempel di tiap sel/tanggal roster & baris tim
     * (host component dispatch 'open-schedule-swap-history' global). */
    #[On('open-schedule-swap-history')]
    public function openModal(): void
    {
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function getRequestsProperty()
    {
        if (! $this->scheduleId) {
            return collect();
        }

        return ShiftSwapRequests::whereHas('reqSchedule.shiftType', fn ($q) => $q->where('schedule_id', $this->scheduleId))
            ->with(['requester', 'target', 'reqSchedule.shiftType', 'targetSchedule.shiftType'])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();
    }

    public function render()
    {
        return view('livewire.presensi.schedule-swap-history-sheet');
    }
}
