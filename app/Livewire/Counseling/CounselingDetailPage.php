<?php

namespace App\Livewire\Counseling;

use App\Enum\CounselingStatus;
use App\Models\Counseling\Counseling;
use App\Services\Counseling\CounselingService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail Counseling (HC) — lihat keluhan + form balas (response+solution) kalau masih PENDING,
 * read-only kalau sudah DONE. Padanan CounselingForm admin, delegasi ke CounselingService::respond().
 */
#[Layout('components.layout', ['title' => 'Detail Counseling'])]
class CounselingDetailPage extends Component
{
    public Counseling $record;
    public string $response = '';
    public string $solution = '';

    public function mount(Counseling $record): void
    {
        abort_unless(Auth::user()->can('View:Counseling'), 403);

        abort_unless(
            Auth::user()->canFilterByCompany() || $record->company_id === Auth::user()->company_id,
            403
        );

        $record->load(['user', 'respondedBy']);

        $this->record = $record;
        $this->response = (string) $record->response;
        $this->solution = (string) $record->solution;
    }

    public function getCanRespondProperty(): bool
    {
        return $this->record->status === CounselingStatus::PENDING->value
            && Auth::user()->can('Update:Counseling');
    }

    public function submitResponse(): void
    {
        abort_unless($this->canRespond, 403);

        $data = $this->validate([
            'response' => ['required', 'string'],
            'solution' => ['required', 'string'],
        ]);

        app(CounselingService::class)->respond($this->record, $data['response'], $data['solution']);

        $this->record->refresh();

        Notification::make()->title('Counseling berhasil dibalas')->success()->send();
    }

    public function render()
    {
        return view('livewire.counseling.counseling-detail-page');
    }
}
