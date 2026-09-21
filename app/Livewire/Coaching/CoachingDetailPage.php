<?php

namespace App\Livewire\Coaching;

use App\Enum\CustomPermissionEnum;
use App\Models\Coaching\Coaching;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail Coaching — read-only. Coaching satu arah (instruksi user 2026-09-22): coach memberi,
 * karyawan yang dibina hanya membaca; tidak ada upload bukti/approve/reject.
 * Yang boleh buka: karyawan yang dibina, coach yang ditunjuk, atau (lewat gerbang modul
 * SISTEM_PEMBINAAN_KARYAWAN) HC / atasan bawahannya.
 */
#[Layout('components.layout', ['title' => 'Detail Coaching'])]
class CoachingDetailPage extends Component
{
    public Coaching $record;

    public function mount(Coaching $record): void
    {
        $user = Auth::user();
        $isSubject = $record->user_id === $user->id;
        $isCoach = $record->coach_by === $user->id;
        $isSubordinateManager = app(OrgHierarchyService::class)->subordinates($user)->where('id', $record->user_id)->exists();

        // Subject-nya sendiri dan coach yang ditunjuk self-contained lewat penunjukan LANGSUNG di
        // record ini - tidak digerbangi SISTEM_PEMBINAAN_KARYAWAN (instruksi user 2026-08-17).
        // Gerbang modul itu cuma wajib untuk jalur "lihat coaching orang lain" (HC company-wide /
        // atasan lewat hierarki bawahan).
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

        $record->load(['user', 'coachBy', 'createdBy']);

        $this->record = $record;
    }

    public function getIsSubjectProperty(): bool
    {
        return $this->record->user_id === Auth::id();
    }

    /** Tombol "Kembali" beda tujuan tergantung yang buka. */
    public function getBackRouteProperty(): string
    {
        return $this->isSubject ? route('coaching.saya') : route('coaching');
    }

    public function render()
    {
        return view('livewire.coaching.coaching-detail-page');
    }
}
