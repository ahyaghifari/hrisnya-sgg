<?php

namespace App\Livewire\Presensi;

use App\Livewire\Presensi\Concerns\FormatsShiftSwapRequest;
use App\Models\Shift\ShiftSwapRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Banner "Tukar Shift Berjalan" di widget "Jadwal Shift Saya" (Dashboard) - pengajuan MILIK
 * SENDIRI yang masih in_review. Sengaja komponen TERPISAH dari ShiftSwapRequestModal (bukan
 * bagian markup tombolnya) - kalau digabung, di desktop banner ikut kejepit dalam flex-row
 * .jadwal-shift-swap-actions (tombol Tukar Shift + Riwayat bersisian), jadi cuma selebar tombol
 * Tukar Shift & Riwayat malah nyangkut di sampingnya (request user 2026-09-16: banner harus
 * span selebar row tombol, Riwayat tidak boleh di samping banner). Dipasang sebagai elemen
 * TERPISAH di atas .jadwal-shift-swap-actions, lihat home-cards.blade.php.
 */
class ShiftSwapOngoingBanner extends Component
{
    use FormatsShiftSwapRequest;

    public function getOngoingSwapsProperty()
    {
        return ShiftSwapRequests::where('status', 'in_review')
            ->where('requester_id', Auth::id())
            ->with(['target', 'reqSchedule.shiftType', 'targetSchedule.shiftType'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.presensi.shift-swap-ongoing-banner');
    }
}
