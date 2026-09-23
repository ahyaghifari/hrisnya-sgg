<?php

namespace App\Livewire\MeritPoint;

use App\Models\MeritPoint\MeritPointTransaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Riwayat Merit Point milik sendiri (planning/2026-09-23-sp-dan-merit-point.md §5.2, jawaban
 * user 2026-09-23) - dibuka lewat ikon "riwayat" di widget tab Karir, bukan lewat nav utama.
 * Ledger tidak pernah diedit, jadi halaman ini murni baca.
 */
#[Layout('components.layout', ['title' => 'Riwayat Merit Point'])]
class MeritPointHistoryPage extends Component
{
    public function render()
    {
        $transactions = MeritPointTransaction::where('user_id', Auth::id())
            ->with('rule')
            ->latest('created_at')
            ->get();

        return view('livewire.merit-point.merit-point-history-page', [
            'transactions' => $transactions,
        ]);
    }
}
