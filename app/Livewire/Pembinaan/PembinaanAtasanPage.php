<?php

namespace App\Livewire\Pembinaan;

use App\Models\Coaching\Coaching;
use App\Models\Counseling\Counseling;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Halaman TAMBAHAN (bukan pengganti modul "Pembinaan Karyawan"/eks-Coaching & Counseling HC di
 * CoachingPage/CounselingManagePage, keduanya tetap utuh) — cuma nampilin Coaching/Counseling
 * yang user ini sendiri ditunjuk jadi coach/konselornya (instruksi user 2026-08-17). Gate
 * literal hasSubordinates() (BUKAN canAccessKaryawanMenu()/permission modul) - orang yang cuma
 * ditunjuk jadi coach/konselor tapi tidak punya bawahan TETAP bisa buka record-nya sendiri lewat
 * coaching.show/counseling.kelola.show (gate ada di detail page masing-masing), cuma tidak dapat
 * halaman ringkasan ini.
 */
#[Layout('components.layout', ['title' => 'Coaching & Counseling'])]
class PembinaanAtasanPage extends Component
{
    use WithPagination;

    #[Url]
    public string $activeTab = 'coaching';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasSubordinates(), 403);
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function getCoachingsProperty()
    {
        return Coaching::where('coach_by', Auth::id())
            ->with('user')
            ->latest()
            ->paginate(10, ['*'], 'coachingPage');
    }

    public function getCounselingsProperty()
    {
        return Counseling::where('counselor_id', Auth::id())
            ->with('user')
            ->latest()
            ->paginate(10, ['*'], 'counselingPage');
    }

    public function render()
    {
        return view('livewire.pembinaan.pembinaan-atasan-page');
    }
}
