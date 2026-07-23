<?php

namespace App\Livewire\Coaching;

use App\Enum\CoachingStatus;
use App\Models\Coaching\Coaching;
use App\Services\Coaching\CoachingService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail Coaching (HC) — read-only info + bukti pelaksanaan (grouped per tanggal) + tombol
 * Approve/Reject kalau status SUBMITTED dan coach_by = user ini (padanan EditCoaching admin).
 */
#[Layout('components.layout', ['title' => 'Detail Coaching'])]
class CoachingDetailPage extends Component
{
    public Coaching $record;

    public function mount(Coaching $record): void
    {
        abort_unless(Auth::user()->can('View:Coaching'), 403);

        abort_unless(
            Auth::user()->canFilterByCompany() || $record->company_id === Auth::user()->company_id,
            403
        );

        $record->load(['user', 'coachBy', 'proofs']);

        $this->record = $record;
    }

    public function getCanReviewProperty(): bool
    {
        return $this->record->status === CoachingStatus::SUBMITTED->value
            && $this->record->coach_by === Auth::id();
    }

    public function approve(): void
    {
        abort_unless($this->canReview, 403);

        app(CoachingService::class)->approve($this->record);

        $this->record->refresh();

        Notification::make()->title('Coaching berhasil ditandai selesai')->success()->send();
    }

    public function reject(): void
    {
        abort_unless($this->canReview, 403);

        app(CoachingService::class)->reject($this->record);

        $this->record->refresh();

        Notification::make()->title('Coaching berhasil ditolak')->success()->send();
    }

    public function render()
    {
        return view('livewire.coaching.coaching-detail-page');
    }
}
