<?php

namespace App\Livewire\Presensi;

use App\Models\Shift\ShiftSwapRequestCancellations;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
use App\Services\OrgHierarchyService;
use App\Services\Shift\ShiftSwapCancellationService;
use App\Services\Shift\ShiftSwapRequestService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail pengajuan tukar shift + approve/reject/cancel - pola sama persis
 * App\Livewire\AttendanceRequestDetail (backend generic sudah lengkap, lihat
 * App\Services\Approval\ApprovalFlowService::advanceStep() yang sudah handle module_type
 * swap_shift_request, dibangun 2026-08-14/15).
 */
#[Layout('components.layout', ['title' => 'Detail Tukar Shift'])]
class ShiftSwapRequestDetail extends Component
{
    public ShiftSwapRequests $record;

    public ?string $pendingAction = null;

    public ?string $note = null;

    /** Aksi self-service pemohon asli (bukan approver): 'self_cancel' (batal langsung, masih
     *  in_review) atau 'request_cancel' (ajukan pembatalan, sudah approved) - lihat
     *  getCanSelfCancelProperty()/getCanRequestCancellationProperty(). */
    public ?string $selfAction = null;
    public ?string $selfActionReason = null;

    /** Approve/Reject untuk pengajuan pembatalan - aktivitas terpisah dari $pendingAction di atas
     *  yang khusus pengajuan tukar shift aslinya. */
    public ?string $cancellationPendingAction = null;
    public ?string $cancellationNote = null;

    public function mount(ShiftSwapRequests $record): void
    {
        $record->load([
            'approvalSnapshot.activity', 'approvalHistories', 'requester', 'target',
            'reqSchedule.shiftType', 'targetSchedule.shiftType',
            'cancellationRequests' => fn ($q) => $q->latest()->limit(1),
        ]);

        abort_unless($this->canAccess($record, Auth::user()), 403);

        $this->record = $record;
    }

    protected function canAccess(ShiftSwapRequests $record, User $user): bool
    {
        if ($record->requester_id === $user->id || $record->target_id === $user->id) {
            return true;
        }

        $snapshot = $record->approvalSnapshot;

        if ($snapshot && app(ApprovalPermissionService::class)->hasPermission($snapshot->id, $user)) {
            return true;
        }

        $cancellationSnapshot = $this->resolveActiveCancellation($record)?->approvalSnapshot;

        if ($cancellationSnapshot && app(ApprovalPermissionService::class)->hasPermission($cancellationSnapshot->id, $user)) {
            return true;
        }

        if ($record->approvalHistories()->where('actor_id', $user->id)->exists()) {
            return true;
        }

        if ($user->canBrowseAllCompanies()) {
            return true;
        }

        return app(OrgHierarchyService::class)
            ->subordinates($user)
            ->whereKey($record->requester_id)
            ->exists();
    }

    public function getCanApproveProperty(): bool
    {
        if (! $this->record->approvalSnapshot) {
            return false;
        }

        return app(ApprovalPermissionService::class)
            ->canApprove($this->record->approvalSnapshot->id, Auth::user());
    }

    /** Pengajuan pembatalan terbaru (kalau ada) untuk record ini - null kalau belum pernah
     *  diajukan. */
    private function resolveActiveCancellation(?ShiftSwapRequests $record = null): ?ShiftSwapRequestCancellations
    {
        $record ??= $this->record;

        return $record->relationLoaded('cancellationRequests')
            ? $record->cancellationRequests->first()
            : $record->cancellationRequests()->latest()->first();
    }

    public function getActiveCancellationProperty(): ?ShiftSwapRequestCancellations
    {
        return $this->resolveActiveCancellation();
    }

    /** Pemohon asli boleh batal LANGSUNG - masih in_review, belum lewat cutoff (tanggal paling
     *  awal dari req_date/target_date). */
    public function getCanSelfCancelProperty(): bool
    {
        return $this->record->requester_id === Auth::id()
            && $this->record->approvalSnapshot?->status === 'in_review'
            && ! app(ShiftSwapRequestService::class)->cancellationCutoffPassed($this->record);
    }

    /** Pemohon asli boleh AJUKAN pembatalan - sudah approved, belum lewat cutoff, belum ada
     *  pengajuan pembatalan yang masih diproses. */
    public function getCanRequestCancellationProperty(): bool
    {
        return $this->record->requester_id === Auth::id()
            && $this->record->status === 'approved'
            && ! app(ShiftSwapRequestService::class)->cancellationCutoffPassed($this->record)
            && $this->activeCancellation?->status !== 'in_review';
    }

    public function getCanApproveCancellationProperty(): bool
    {
        $snapshot = $this->activeCancellation?->approvalSnapshot;

        if (! $snapshot) {
            return false;
        }

        return app(ApprovalPermissionService::class)->canApprove($snapshot->id, Auth::user());
    }

    public function getCancellationTimelineProperty(): array
    {
        return $this->activeCancellation
            ? app(ApprovalTimelineService::class)->build($this->activeCancellation)
            : [];
    }

    public function chooseAction(string $type): void
    {
        $this->pendingAction = $type;
        $this->note = null;
        $this->resetErrorBag();
    }

    public function cancelChoice(): void
    {
        $this->pendingAction = null;
        $this->note = null;
    }

    public function chooseSelfAction(string $type): void
    {
        $this->selfAction = $type;
        $this->selfActionReason = null;
        $this->resetErrorBag();
    }

    public function cancelSelfAction(): void
    {
        $this->selfAction = null;
        $this->selfActionReason = null;
    }

    public function confirmSelfAction(): void
    {
        $this->validate(['selfActionReason' => ['required', 'string']], [], ['selfActionReason' => 'Alasan']);

        // Guard server-side - tombolnya sudah disembunyikan di blade kalau tidak eligible, tapi
        // statusnya dicek ulang di sini (bukan cuma andalkan UI), pola sama
        // AttendanceRequestDetail::confirmSelfAction().
        abort_unless(
            ($this->selfAction === 'self_cancel' && $this->canSelfCancel)
                || ($this->selfAction === 'request_cancel' && $this->canRequestCancellation),
            403
        );

        try {
            if ($this->selfAction === 'self_cancel') {
                app(ApprovalFlowService::class)->advanceStep(
                    $this->record->approvalSnapshot,
                    Auth::id(),
                    $this->selfActionReason,
                    'cancelled'
                );

                Notification::make()->title('Pengajuan berhasil dibatalkan')->success()->send();
            } elseif ($this->selfAction === 'request_cancel') {
                app(ShiftSwapCancellationService::class)->requestCancellation(
                    $this->record,
                    Auth::user(),
                    $this->selfActionReason
                );

                Notification::make()->title('Pengajuan pembatalan berhasil dikirim, menunggu persetujuan')->success()->send();
            }

            $this->selfAction = null;
            $this->selfActionReason = null;

            $this->record->refresh();
            $this->record->load([
                'approvalSnapshot.activity', 'approvalHistories', 'requester', 'target',
                'reqSchedule.shiftType', 'targetSchedule.shiftType',
                'cancellationRequests' => fn ($q) => $q->latest()->limit(1),
            ]);

            unset($this->canSelfCancel, $this->canRequestCancellation, $this->activeCancellation, $this->cancellationTimeline);
        } catch (\Exception $e) {
            Notification::make()->title('Gagal: ' . $e->getMessage())->danger()->send();
        }
    }

    public function chooseCancellationAction(string $type): void
    {
        $this->cancellationPendingAction = $type;
        $this->cancellationNote = null;
        $this->resetErrorBag();
    }

    public function cancelCancellationChoice(): void
    {
        $this->cancellationPendingAction = null;
        $this->cancellationNote = null;
    }

    /** Approve/Reject oleh approver ALUR PEMBATALAN (bisa beda orang dari approver pengajuan
     *  aslinya) - terpisah dari confirmAction() yang menangani pengajuan tukar shift asli. */
    public function confirmCancellationAction(): void
    {
        $this->validate(['cancellationNote' => ['required', 'string']], [], ['cancellationNote' => 'Catatan']);

        $actionMap = ['approve' => 'approved', 'reject' => 'rejected'];
        $action = $actionMap[$this->cancellationPendingAction] ?? null;

        if (! $action) {
            return;
        }

        abort_unless($this->canApproveCancellation, 403);

        try {
            app(ApprovalFlowService::class)->advanceStep(
                $this->activeCancellation->approvalSnapshot,
                Auth::id(),
                $this->cancellationNote,
                $action
            );

            $this->cancellationPendingAction = null;
            $this->cancellationNote = null;

            $this->record->refresh();
            $this->record->load([
                'approvalSnapshot.activity', 'approvalHistories', 'requester', 'target',
                'reqSchedule.shiftType', 'targetSchedule.shiftType',
                'cancellationRequests' => fn ($q) => $q->latest()->limit(1),
            ]);

            unset($this->canApproveCancellation, $this->activeCancellation, $this->cancellationTimeline);

            Notification::make()->title('Berhasil diproses')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Gagal memproses: ' . $e->getMessage())->danger()->send();
        }
    }

    public function confirmAction(): void
    {
        $this->validate(['note' => ['required', 'string']]);

        $actionMap = ['approve' => 'approved', 'reject' => 'rejected', 'cancel' => 'cancelled'];
        $action = $actionMap[$this->pendingAction] ?? null;

        if (! $action) {
            return;
        }

        $stepOrderBefore = (int) ($this->record->approvalSnapshot->activity?->current_step_order ?? 0);

        try {
            app(ApprovalFlowService::class)->advanceStep(
                $this->record->approvalSnapshot,
                Auth::id(),
                $this->note,
                $action
            );

            // Kalau yang barusan approve/reject adalah rekan di step-nya sendiri, isi juga kolom
            // "Tanggapan Rekan" biar tidak nyangkut "Belum menanggapi".
            app(ShiftSwapRequestService::class)->syncTargetResponseFromApprovalStep(
                $this->record,
                Auth::id(),
                $stepOrderBefore,
                $action,
            );

            $this->pendingAction = null;
            $this->note = null;

            $this->record->refresh();
            $this->record->load(['approvalSnapshot.activity', 'approvalHistories', 'requester', 'target', 'reqSchedule.shiftType', 'targetSchedule.shiftType']);

            Notification::make()
                ->title('Berhasil diproses')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal memproses: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getTimelineProperty(): array
    {
        return app(ApprovalTimelineService::class)->build($this->record);
    }

    /** Peringatan double shift/nyambung shift (tanggal beda + salah satu pihak sudah punya
     * jadwal lain di tanggal tujuan) - dihitung ulang live tiap render, ditampilkan ke pengaju,
     * rekan, maupun approver. Extra Shift TIDAK dibuat otomatis lagi (2026-09-12) - kalau
     * memang perlu, atasan bikin manual lewat halaman Extra Shift. */
    public function getConflictWarningsProperty(): array
    {
        return app(ShiftSwapRequestService::class)->conflictWarnings($this->record);
    }

    /** Label "Rekan: Setuju/Menolak/Belum menanggapi" - murni info, TIDAK mempengaruhi
     * canApprove/approve-reject sama sekali (lihat App\Services\Shift\ShiftSwapRequestService). */
    public function getTargetResponseLabelProperty(): string
    {
        return match ($this->record->target_response) {
            'accepted' => 'Setuju',
            'declined' => 'Menolak',
            default => 'Belum menanggapi',
        };
    }

    public function render()
    {
        return view('livewire.presensi.shift-swap-request-detail');
    }
}
