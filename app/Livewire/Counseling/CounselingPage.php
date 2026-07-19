<?php

namespace App\Livewire\Counseling;

use App\Models\Counseling\Counseling;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Counseling (personal) — riwayat keluhan milik sendiri. Belum ada form submit issue —
 * lihat planning/2026-07-18-portal-coaching-counseling.md §4.2.
 */
#[Layout('components.layout', ['title' => 'Counseling'])]
class CounselingPage extends Component
{
    public function render()
    {
        $counselings = Counseling::where('user_id', Auth::id())
            ->with('respondedBy')
            ->latest()
            ->get();

        return view('livewire.counseling.counseling-page', [
            'counselings' => $counselings,
        ]);
    }
}
