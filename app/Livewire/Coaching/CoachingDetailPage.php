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
        $isCoach = $record->coach_by === $user->id;
        $isSubordinateManager = app(OrgHierarchyService::class)->subordinates($user)->where('id', $record->user_id)->exists();

        // Subject-nya sendiri (baca/upload bukti) DAN coach yang ditunjuk (review/approve/reject)
        // dua-duanya self-contained lewat penunjukan LANGSUNG di record ini - tidak digerbangi
        // SISTEM_PEMBINAAN_KARYAWAN (instruksi user 2026-08-17: konyol kalau orang yang cuma
        // ditunjuk jadi coach 1 record harus dikasih akses SELURUH modul Coaching & Counseling
        // dulu). Yang WAJIB punya gerbang modul itu cuma jalur "lihat coaching orang lain"
        // (HC company-wide / atasan lewat hierarki bawahan), BUKAN pihak yang memang terlibat
        // langsung di record ini.
        abort_unless(
            $isSubject
                || $isCoach
                || (
                    $user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value)
                    && (
                        $user->can('View:Coaching')
                        || $user->can(CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA->value)
                        || $isSubordinateManager
                    )
                ),
            403
        );

        abort_unless(
            $isSubject || $isCoach || $user->canFilterByCompany() || $record->company_id === $user->company_id,
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
        // Cek langsung ke record (BUKAN lewat $this->canUpload) - computed property getX...
        // Property Livewire di-memoize sekali per request begitu diakses; kalau guard ini pakai
        // $this->canUpload, nilainya ke-cache dan dipakai ulang saat render() nanti walau status
        // sudah berubah di bawah - form/tombol jadi nyangkut kelihatan padahal sudah dipakai.
        abort_unless(
            $this->isSubject && in_array($this->record->status, [
                CoachingStatus::IN_PROGRESS->value,
                CoachingStatus::REJECTED->value,
            ], true),
            403
        );

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
        // Sama alasan seperti submitProofs() - cek langsung, jangan lewat $this->canReview.
        abort_unless(
            $this->record->status === CoachingStatus::SUBMITTED->value && $this->record->coach_by === Auth::id(),
            403
        );

        app(CoachingService::class)->approve($this->record);

        $this->record->refresh();

        Notification::make()->title('Coaching berhasil ditandai selesai')->success()->send();
    }

    public function reject(): void
    {
        // Sama alasan seperti submitProofs() - cek langsung, jangan lewat $this->canReview.
        abort_unless(
            $this->record->status === CoachingStatus::SUBMITTED->value && $this->record->coach_by === Auth::id(),
            403
        );

        app(CoachingService::class)->reject($this->record);

        $this->record->refresh();

        Notification::make()->title('Coaching berhasil ditolak')->success()->send();
    }

    public function render()
    {
        return view('livewire.coaching.coaching-detail-page');
    }
}
