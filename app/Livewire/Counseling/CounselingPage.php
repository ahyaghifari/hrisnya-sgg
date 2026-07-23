<?php

namespace App\Livewire\Counseling;

use App\Models\Counseling\Counseling;
use App\Services\Counseling\CounselingService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Counseling (personal) — riwayat keluhan milik sendiri + form ajukan baru lewat modal
 * ("Konsul"). Reuse App\Services\Counseling\CounselingService::create() — sama service yang
 * dipakai App\Http\Controllers\Api\Counseling\CounselingController (mobile).
 */
#[Layout('components.layout', ['title' => 'Counseling'])]
class CounselingPage extends Component
{
    public bool $showConsultModal = false;
    public string $issue = '';

    public function openConsultModal(): void
    {
        $this->issue = '';
        $this->showConsultModal = true;
    }

    public function closeConsultModal(): void
    {
        $this->showConsultModal = false;
    }

    public function submitConsult(): void
    {
        $data = $this->validate([
            'issue' => ['required', 'string', 'max:2000'],
        ]);

        app(CounselingService::class)->create((object) ['issue' => $data['issue']]);

        $this->showConsultModal = false;
        Notification::make()->title('Konsul berhasil diajukan')->success()->send();
    }

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
