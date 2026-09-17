<?php

namespace App\Livewire\Rekrutmen;

use App\Enum\JobPositionReqStatus;
use App\Models\Job\ManpowerRequests;
use App\Models\User;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
use App\Services\JobPosition\ManPowerReqService;
use App\Services\OrgHierarchyService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail Permintaan Tenaga Kerja + approve/reject — pola persis UserTransitionDetailPage.
 * Beda dari admin (EditManpowerRequest): admin cuma punya approve, di sini juga ada reject
 * (paritas dengan modul approval lain — lihat catatan planning/eager-pondering-turtle.md).
 *
 * Gate akses (canAccess()): JADI APPROVER SAJA CUKUP, tidak WAJIB Shield permission
 * View:ManpowerRequests (2026-08-09, dulu blanket can('View:ManpowerRequests') blokir approver
 * legit yang kebetulan role-nya tidak di-assign permission itu) — pola disamakan dengan
 * AttendanceRequestDetail::canAccess(). Permission View:ManpowerRequests tetap salah satu jalur
 * (OR, bukan syarat wajib) buat siapa pun yang role-nya di-assign lewat sistem Kepegawaian.
 * Kolom requested_by SENGAJA TIDAK dijadikan gerbang akses sendiri (instruksi user 2026-09-17) -
 * siapa yang bikin request bukan patokan hak lihat, itu urusan permission di atas.
 */
#[Layout('components.layout', ['title' => 'Detail Permintaan Tenaga Kerja'])]
class ManpowerRequestDetailPage extends Component
{
    public ManpowerRequests $record;
    public string $note = '';

    public function mount(ManpowerRequests $record): void
    {
        app(ManPowerReqService::class)->ensureApprovalSnapshot($record);

        $record->load(['company', 'jobPosition.orgStructure', 'user', 'approvalSnapshot.activity', 'approvalHistories']);

        abort_unless($this->canAccess($record, Auth::user()), 403);

        $this->record = $record;
    }

    protected function canAccess(ManpowerRequests $record, User $user): bool
    {
        // Akses "pembuat request" DIHAPUS (instruksi user 2026-09-17) - siapa yang klik "Buat
        // Permintaan" bukan patokan hak lihat, itu urusan role/permission yang dikelola di
        // sistem Kepegawaian (Shield View:ManpowerRequests), bukan kolom requested_by.
        if ($user->can('View:ManpowerRequests')) {
            return true;
        }

        $snapshot = $record->approvalSnapshot;

        if ($snapshot && app(ApprovalPermissionService::class)->hasPermission($snapshot->id, $user)) {
            return true;
        }

        if ($record->approvalHistories()->where('actor_id', $user->id)->exists()) {
            return true;
        }

        if ($user->canBrowseAllCompanies()) {
            return true;
        }

        if ($record->company_id !== $user->company_id) {
            return false;
        }

        return app(OrgHierarchyService::class)
            ->subordinates($user)
            ->whereKey($record->requested_by)
            ->exists();
    }

    public function getTimelineProperty(): array
    {
        return app(ApprovalTimelineService::class)->build($this->record);
    }

    public function getCanApproveProperty(): bool
    {
        if (! $this->record->approvalSnapshot || $this->record->status !== JobPositionReqStatus::REQUESTED->value) {
            return false;
        }

        return app(ApprovalPermissionService::class)->canApprove($this->record->approvalSnapshot->id, Auth::user());
    }

    public function approve(): void
    {
        abort_unless($this->canApprove, 403);

        try {
            app(ManPowerReqService::class)->approveManPower(
                manpowerRequests: $this->record,
                status: JobPositionReqStatus::APPROVED->value,
                note: $this->note,
            );

            Notification::make()->title('Permintaan berhasil disetujui')->success()->send();

            $this->redirect(route('dashboard'), navigate: true);
        } catch (\Exception $e) {
            Notification::make()->title('Gagal menyetujui: ' . $e->getMessage())->danger()->send();
        }
    }

    public function reject(): void
    {
        abort_unless($this->canApprove, 403);

        try {
            app(ManPowerReqService::class)->reject($this->record, $this->note, Auth::id());

            Notification::make()->title('Permintaan ditolak')->success()->send();

            $this->redirect(route('dashboard'), navigate: true);
        } catch (\Exception $e) {
            Notification::make()->title('Gagal menolak: ' . $e->getMessage())->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.rekrutmen.manpower-request-detail-page');
    }
}
