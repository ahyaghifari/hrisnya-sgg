<?php

namespace App\Livewire\Coaching;

use App\Enum\CoachingStatus;
use App\Enum\CustomPermissionEnum;
use App\Models\Coaching\Coaching;
use App\Services\Coaching\CoachingService;
use App\Services\OrgHierarchyService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Detail Coaching — SATU halaman, TIGA kemungkinan peran (instruksi user 2026-07-26, jangan
 * dipisah jadi halaman baru per peran):
 * - Coach (coach_by): approve/reject bukti kalau status SUBMITTED.
 * - Atasan/HC (permission View:Coaching atau bawahannya): read-only, sama seperti sebelumnya.
 * - Karyawan yang dicoaching (user_id = subject-nya sendiri): read-only info + form upload
 *   bukti pelaksanaan kalau status IN_PROGRESS/REJECTED (sebelumnya "Coaching Saya" cuma list
 *   placeholder tanpa link kemana pun, tidak ada cara upload bukti sama sekali).
 */
#[Layout('components.layout', ['title' => 'Detail Coaching'])]
class CoachingDetailPage extends Component
{
    use WithFileUploads;

    public Coaching $record;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $proofs = [];

    public function mount(Coaching $record): void
    {
        $user = Auth::user();
        $isSubject = $record->user_id === $user->id;
        $isInvolvedAsSupervisor = $record->coach_by === $user->id
            || app(OrgHierarchyService::class)->subordinates($user)->where('id', $record->user_id)->exists();

        // Subject-nya sendiri (baca/upload bukti coaching miliknya) tidak digerbangi
        // SISTEM_COACHING_COUNSELING - itu gerbang khusus sisi atasan/coach/HC, bukan
        // self-service. Kalau bukan subject, wajib punya gerbang itu DULU baru dicek jalur akses
        // atasan/coach-nya.
        abort_unless(
            $isSubject
                || (
                    $user->can(CustomPermissionEnum::SISTEM_COACHING_COUNSELING->value)
                    && (
                        $user->can('View:Coaching')
                        || $user->can(CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA->value)
                        || $isInvolvedAsSupervisor
                    )
                ),
            403
        );

        abort_unless(
            $isSubject || $user->canFilterByCompany() || $record->company_id === $user->company_id,
            403
        );

        $record->load(['user', 'coachBy', 'proofs']);

        $this->record = $record;
    }

    public function getIsSubjectProperty(): bool
    {
        return $this->record->user_id === Auth::id();
    }

    public function getCanReviewProperty(): bool
    {
        return $this->record->status === CoachingStatus::SUBMITTED->value
            && $this->record->coach_by === Auth::id();
    }

    /** Subject boleh upload bukti selagi IN_PROGRESS, atau upload ULANG kalau kemarin REJECTED. */
    public function getCanUploadProperty(): bool
    {
        return $this->isSubject && in_array($this->record->status, [
            CoachingStatus::IN_PROGRESS->value,
            CoachingStatus::REJECTED->value,
        ], true);
    }

    /** Tombol "Kembali" beda tujuan tergantung yang buka. */
    public function getBackRouteProperty(): string
    {
        return $this->isSubject ? route('coaching.saya') : route('coaching');
    }

    public function submitProofs(): void
    {
        abort_unless($this->canUpload, 403);

        $data = $this->validate([
            'proofs' => ['required', 'array', 'min:1'],
            'proofs.*' => ['image', 'max:5120'],
        ]);

        app(CoachingService::class)->uploadProofs($this->record, $data['proofs']);

        $this->proofs = [];
        $this->record->refresh()->load('proofs');

        Notification::make()->title('Bukti berhasil diajukan, menunggu ditinjau coach')->success()->send();
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
