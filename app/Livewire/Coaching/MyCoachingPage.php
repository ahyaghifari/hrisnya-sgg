<?php

namespace App\Livewire\Coaching;

use App\Models\Coaching\Coaching;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Coaching Saya" — riwayat coaching di mana user_id = auth()->id() (sisi sedang dibina).
 * Punya SEMUA orang, tidak digate Create:Coaching. List saja (belum ada upload bukti/detail
 * penuh) — lihat planning/2026-07-18-portal-coaching-counseling.md §4.4.
 */
#[Layout('components.layout', ['title' => 'Coaching Saya'])]
class MyCoachingPage extends Component
{
    public function render()
    {
        $coachings = Coaching::where('user_id', Auth::id())
            ->with('coachBy')
            ->latest()
            ->get();

        return view('livewire.coaching.my-coaching-page', [
            'coachings' => $coachings,
        ]);
    }
}
