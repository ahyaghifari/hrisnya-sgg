<?php

namespace App\Livewire\Coaching;

use App\Enum\CoachingFollowUpStatus;
use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpSourceType;
use App\Models\Coaching\Coaching;
use App\Models\Sp\EmployeeSp;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Filament\Notifications\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail Coaching — read-only untuk kartu info dasar. Coaching satu arah (instruksi user
 * 2026-09-22): coach memberi, karyawan yang dibina hanya membaca; tidak ada upload
 * bukti/approve/reject. Yang boleh buka: karyawan yang dibina, coach yang ditunjuk, atau (lewat
 * gerbang modul SISTEM_PEMBINAAN_KARYAWAN) HC / atasan staff-nya.
 *
 * Tindak Lanjut (§1.1, HANYA coach_by yang login) - evaluasi kondisi karyawan, 2 pilihan, sekadar
 * catatan. Coaching & SP sengaja dipisah total (instruksi user 2026-09-23) - halaman ini TIDAK
 * punya aksi "rekomendasikan SP" lagi (dulu section "Tindakan Lanjutan", dihapus karena mirip
 * nama & membingungkan dengan "Tindak Lanjut"). SP dibuat dari sisi SP sendiri
 * (CreateSpPage/EmployeeSpResource), yang bisa pilih coaching ini sebagai sumbernya - kartu
 * Tindak Lanjut di sini cuma menampilkan SP itu (read-only) kalau ada, lewat getLinkedSpProperty().
 */
#[Layout('components.layout', ['title' => 'Detail Coaching'])]
class CoachingDetailPage extends Component
{
    public Coaching $record;

    public ?string $followUpStatus = null;
    public ?string $followUpNote = null;

    public function mount(Coaching $record): void
    {
        $user = Auth::user();
        $isSubject = $record->user_id === $user->id;
        $isCoach = $record->coach_by === $user->id;
        $isUserCreate = $record->created_by === $user->id;
        $isSubordinateManager = app(OrgHierarchyService::class)->subordinates($user)->where('id', $record->user_id)->exists();

        // Subject-nya sendiri dan coach yang ditunjuk self-contained lewat penunjukan LANGSUNG di
        // record ini - tidak digerbangi SISTEM_PEMBINAAN_KARYAWAN (instruksi user 2026-08-17).
        // Gerbang modul itu cuma wajib untuk jalur "lihat coaching orang lain" (HC company-wide /
        // atasan lewat hierarki staff).
        abort_unless(
            $isSubject
                || $isCoach || $isUserCreate
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
            $isSubject || $isCoach || $isUserCreate || $user->canFilterByCompany() || $record->company_id === $user->company_id,
            403
        );

        $record->load(['user', 'coachBy', 'createdBy']);

        $this->record = $record;
        $this->followUpStatus = $record->follow_up_status?->value;
        $this->followUpNote = $record->follow_up_note;
    }

    public function getIsSubjectProperty(): bool
    {
        return $this->record->user_id === Auth::id();
    }

    public function getIsCoachProperty(): bool
    {
        return $this->record->coach_by === Auth::id();
    }

    public function getIsUserCreateProperty(): bool
    {
        return $this->record->created_by === Auth::id();
    }

    /** Tombol "Kembali" beda tujuan tergantung yang buka. */
    public function getBackRouteProperty(): string
    {
        return $this->isSubject ? route('profil-saya') : route('coaching');
    }

    /** §1.1 - cuma coach_by yang boleh isi, sekadar catatan, BUKAN gerbang aksi SP. */
    public function saveFollowUp(): void
    {
        abort_unless($this->isCoach || $this->isUserCreate, 403);

        $data = $this->validate([
            'followUpStatus' => ['required', Rule::enum(CoachingFollowUpStatus::class)],
            'followUpNote' => ['nullable', 'string'],
        ]);

        $this->record->update([
            'follow_up_status' => $data['followUpStatus'],
            'follow_up_note' => $data['followUpNote'],
            'follow_up_at' => now(),
        ]);

        Notification::make()->title('Tindak lanjut tersimpan')->success()->send();
    }

    /** SP yang sumbernya coaching ini (dipilih dari sisi SP saat dibuat) - read-only, tidak ada
     * aksi buat di sini. Null kalau belum ada SP yang mengacu ke coaching ini. */
    public function getLinkedSpProperty(): ?EmployeeSp
    {
        return EmployeeSp::where('source_type', SpSourceType::COACHING->value)
            ->where('source_id', $this->record->id)
            ->first();
    }

    public function render()
    {
        return view('livewire.coaching.coaching-detail-page');
    }
}
