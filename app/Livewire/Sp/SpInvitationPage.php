<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Models\Invitation;
use App\Models\User;
use App\Services\SikartapReviewerResolver;
use App\Services\Sp\SpInvitationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Undangan klarifikasi (tahap 1 alur SP): tanpa $record = form buat, dengan $record = detail
 * (ubah jadwal, batalkan, lanjut buat Berita Acara). Aturan ada di SpInvitationService.
 */
#[Layout('components.layout', ['title' => 'Undangan Klarifikasi'])]
class SpInvitationPage extends Component
{
    public ?Invitation $record = null;

    public bool $editing = false;

    public ?string $user_id = null;
    public ?string $scheduled_at = null;
    public ?string $location = null;
    public ?string $note = null;

    public string $cancel_reason = '';

    public function mount(?Invitation $record = null): void
    {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value) && $user->can('Create:EmployeeSp'), 403);
        abort_if($record?->exists && $record->company_id !== $user->company_id, 403);

        $this->record = $record?->exists ? $record : null;
        $this->editing = $this->record === null;

        if ($this->record) {
            $this->fillFromRecord();
        }
    }

    private function fillFromRecord(): void
    {
        $this->user_id = $this->record->user_id;
        $this->scheduled_at = $this->record->scheduled_at->format('Y-m-d\TH:i');
        $this->location = $this->record->location;
        $this->note = $this->record->note;
    }

    public function getEmployeeOptionsProperty()
    {
        return User::whereCompany(Auth::user()->company_id)->orderByName()->get()->pluck('name', 'id');
    }

    /** Atasan langsung karyawan terpilih - ikut diundang. */
    public function getSupervisorProperty(): ?User
    {
        $employee = $this->record?->user ?? User::whereCompany(Auth::user()->company_id)->find($this->user_id);

        return $employee ? app(SikartapReviewerResolver::class)->directSupervisorOf($employee) : null;
    }

    public function getIsOpenProperty(): bool
    {
        return $this->record?->isOpen() ?? false;
    }

    public function edit(): void
    {
        abort_unless($this->isOpen, 403);
        $this->editing = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'user_id' => $this->record ? ['nullable'] : ['required', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $service = app(SpInvitationService::class);

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

        Notification::make()->title('Undangan dikirim')->body('Karyawan dan atasan langsung sudah dikabari.')->success()->send();
        $this->redirect(route('sp.undangan.show', $invitation), navigate: true);
    }

    public function cancel(): void
    {
        try {
            app(SpInvitationService::class)->cancel($this->record, Auth::user(), $this->cancel_reason);
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
        return view('livewire.sp.sp-invitation-page');
    }
}
