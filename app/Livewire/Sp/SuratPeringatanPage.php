<?php

namespace App\Livewire\Sp;

use App\Models\Sp\EmployeeSp;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Surat Peringatan Saya" — riwayat LENGKAP SP milik user_id = auth()->id(), termasuk yang sudah
 * expired (dijawab user 2026-09-23, planning/2026-09-23-sp-dan-merit-point.md §5.2) - bukan cuma
 * yang aktif. Route baru, terpisah dari Coaching Saya (§2.1).
 */
#[Layout('components.layout', ['title' => 'Surat Peringatan'])]
class SuratPeringatanPage extends Component
{
    public function render()
    {
        $sps = EmployeeSp::where('user_id', Auth::id())
            ->with('items')
            ->orderByDesc('start_date')
            ->get();

        return view('livewire.sp.surat-peringatan-page', [
            'sps' => $sps,
        ]);
    }
}
