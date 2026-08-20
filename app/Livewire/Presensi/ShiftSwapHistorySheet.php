<?php

namespace App\Livewire\Presensi;

use App\Livewire\Presensi\Concerns\FormatsShiftSwapRequest;
use App\Models\Shift\ShiftSwapRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Riwayat tukar shift MILIK SENDIRI, versi ringkas dalam modal sheet - nempel di widget "Jadwal
 * Shift Saya" (Dashboard), instruksi user 2026-08-20 (link ke halaman penuh /tukar-shift dirasa
 * kurang cepat). 20 baris terbaru saja, tanpa filter status/pagination - link "Lihat Semua
 * Riwayat" di bawah tetap arah ke MyShiftSwapRequests (halaman penuh) buat yang butuh filter.
 * Format baris (keterangan/status) dibagi lewat FormatsShiftSwapRequest biar tidak dobel sama
 * MyShiftSwapRequests.
 */
class ShiftSwapHistorySheet extends Component
{
    use FormatsShiftSwapRequest;

    public bool $showModal = false;

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
        return ShiftSwapRequests::where('requester_id', Auth::id())
            ->with(['target', 'reqSchedule.shiftType', 'targetSchedule.shiftType'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    public function render()
    {
        return view('livewire.presensi.shift-swap-history-sheet');
    }
}
