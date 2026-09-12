<?php

namespace App\Livewire\Presensi;

use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
use App\Services\OrgHierarchyService;
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

    public function mount(ShiftSwapRequests $record): void
    {
        $record->load(['approvalSnapshot.activity', 'approvalHistories', 'requester', 'target', 'reqSchedule.shiftType', 'targetSchedule.shiftType']);

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
