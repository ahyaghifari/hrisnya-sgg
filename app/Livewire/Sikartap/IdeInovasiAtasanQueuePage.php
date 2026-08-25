<?php

namespace App\Livewire\Sikartap;

use App\Models\Sikartap\Sikartap;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Antrian ide inovasi yang perlu dinilai user ini sebagai atasan — lihat
 * Sikartap::ideInovasiAtasanCandidates() buat gimana "atasan" ditentukan. */
#[Layout('components.layout', ['title' => 'Penilaian Ide Inovasi'])]
class IdeInovasiAtasanQueuePage extends Component
{
    public function getQueueProperty()
    {
        $user = Auth::user();

        return Sikartap::with(['user', 'ideInovasi'])
            ->whereHas('ideInovasi', fn ($q) => $q->where('status_atasan', 'pending'))
            ->get()
            ->filter(fn (Sikartap $sikartap) => $sikartap->canReviewIdeInovasiAsAtasan($user))
            ->values();
    }

    public function render()
    {
        return view('livewire.sikartap.ide-inovasi-atasan-queue-page');
    }
}
