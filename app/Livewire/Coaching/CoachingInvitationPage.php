<?php

namespace App\Livewire\Coaching;

use App\Enum\InvitationType;
use App\Models\Invitation;
use App\Services\Coaching\CoachingInvitationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Undangan coaching: tanpa $record = form buat, dengan $record = detail (ubah jadwal, batalkan).
 * Aturan ada di CoachingInvitationService.
 */
#[Layout('components.layout', ['title' => 'Undangan Coaching'])]
class CoachingInvitationPage extends Component
{
    public ?Invitation $record = null;

    public bool $editing = false;

    public ?string $user_id = null;
    public ?string $scheduled_at = null;
    public ?string $location = null;
    public ?string $note = null;
    public bool $with_supervisor = true; // default: atasan langsung ikut diundang

    public string $cancel_reason = '';

    public function mount(?Invitation $record = null): void
    {
        $service = app(CoachingInvitationService::class);

        abort_unless($service->canCreate(Auth::user()), 403);

        if ($record?->exists) {
            abort_unless($record->type === InvitationType::COACHING && $record->company_id === Auth::user()->company_id, 404);
            abort_unless($record->created_by === Auth::id() || $service->hasFullAccess(Auth::user()), 403);

            $this->record = $record;
            $this->user_id = $record->user_id;
            $this->scheduled_at = $record->scheduled_at->format('Y-m-d\TH:i');
            $this->location = $record->location;
            $this->note = $record->note;
            $this->with_supervisor = $record->supervisor_id !== null;
        } else {
            $this->editing = true;

            // Prefill dari tombol pintasan di halaman coaching (cuma kenyamanan UI; cakupan dicek ulang saat simpan).
            if ($prefill = request()->query('user_id')) {
                $this->user_id = $service->coacheeOptions(Auth::user())->has($prefill) ? $prefill : null;
            }
        }
    }

    public function getCoacheeOptionsProperty()
    {
        return app(CoachingInvitationService::class)->coacheeOptions(Auth::user());
    }

    /** Atasan langsung coachee terpilih (pratinjau untuk pilihan "undang atasan"). */
    public function getSupervisorProperty(): ?\App\Models\User
    {
        $coachee = $this->record?->user ?? ($this->user_id ? \App\Models\User::find($this->user_id) : null);

        return $coachee ? app(\App\Services\SikartapReviewerResolver::class)->directSupervisorOf($coachee) : null;
    }

    public function getCanManageProperty(): bool
    {
        return $this->record !== null && app(CoachingInvitationService::class)->canManage($this->record, Auth::user());
    }

    public function edit(): void
    {
        abort_unless($this->canManage, 403);
        $this->editing = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'user_id' => $this->record ? ['nullable'] : ['required', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'with_supervisor' => ['boolean'],
        ], [], ['scheduled_at' => 'waktu', 'location' => 'tempat', 'note' => 'topik']);

        $service = app(CoachingInvitationService::class);

        try {
            if ($this->record) {
                $service->update($this->record, Auth::user(), $data);
                Notification::make()->title('Undangan diperbarui')->success()->send();
                $this->record->refresh();
                $this->editing = false;

                return;
            }

            $invitation = $service->create(Auth::user(), $data);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Notification::make()->title('Undangan dikirim')->body('Karyawan sudah dikabari.')->success()->send();
        $this->redirect(route('coaching.undangan.show', $invitation), navigate: true);
    }

    public function cancel(): void
    {
        abort_unless($this->canManage, 403);

        try {
            app(CoachingInvitationService::class)->cancel($this->record, Auth::user(), $this->cancel_reason);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Notification::make()->title('Undangan dibatalkan')->success()->send();
        $this->record->refresh();
        $this->cancel_reason = '';
    }

    public function render()
    {
        return view('livewire.coaching.coaching-invitation-page');
    }
}