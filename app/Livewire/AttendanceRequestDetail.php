<?php

namespace App\Livewire;

use App\Models\Attendance\AttendanceRequests;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
use App\Services\Attendance\AttendaceReqService;
use App\Services\OrgHierarchyService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AttendanceRequestDetail extends Component
{
    public AttendanceRequests $record;

    public ?string $pendingAction = null;
    public ?string $note = null;

    public function mount(AttendanceRequests $record): void
    {
        $record->load(['approvalSnapshot.activity', 'approvalHistories', 'attachments']);

        abort_unless($this->canAccess($record, Auth::user()), 403);

        $this->record = $record;
    }

    protected function canAccess(AttendanceRequests $record, User $user): bool
    {
        if ($record->user_id === $user->id) {
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
            ->whereKey($record->user_id)
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

        try {
            app(ApprovalFlowService::class)->advanceStep(
                $this->record->approvalSnapshot,
                Auth::id(),
                $this->note,
                $action
            );

            $this->pendingAction = null;
            $this->note = null;

            $this->record->refresh();
            $this->record->load(['approvalSnapshot.activity', 'approvalHistories', 'attachments']);

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

    /**
     * Info kuota pemohon (Cuti Tahunan/Izin) buat ditampilkan di halaman approval — approver
     * bisa lihat sisa jatah pemohon SAAT itu juga, bukan widget terpisah. Lihat
     * planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §8. Cuma relevan
     * untuk Cuti & Izin — Lembur tidak punya konsep kuota.
     */
    public function getQuotaInfoProperty(): ?array
    {
        $service = app(AttendaceReqService::class);

        if ($this->record->type === 'leave' && $this->record->leaveDetails->isNotEmpty()) {
            $leave = $this->record->leaveDetails->first();

            return [
                'kind' => 'leave',
                'balance' => $service->getLeaveUserBalance($this->record->user_id, $leave->leave_type_id),
                'periode' => $service->getCutiTahunanPeriodeUsage($this->record->user, $leave->leave_type_id, $leave->start_date),
            ];
        }

        if ($this->record->type === 'permission' && $this->record->permissionDetails->isNotEmpty()) {
            $permission = $this->record->permissionDetails->first();
            $category = $permission->category ? \App\Enum\Attendance\PermissionTypeCategoryEnum::tryFrom($permission->category) : null;
            $group = $category ? \App\Enum\Attendance\PermissionGroupEnum::fromCategory($category) : null;

            // Tanpa category (jenis izin custom lama tanpa kategori) tidak ada kelompok yang
            // bisa ditentukan - getIzinPeriodeUsage() sekarang wajib PermissionGroupEnum, jadi
            // info kuota memang tidak relevan/tidak bisa dihitung untuk kasus ini.
            if (! $group) {
                return null;
            }

            $usage = $service->getIzinPeriodeUsage($this->record->user, $group, $permission->date);

            if (! $usage) {
                return null;
            }

            return [
                'kind' => 'permission',
                'usage' => $usage,
            ];
        }

        return null;
    }

    /** Warning masa kerja minimal (AttendanceSettingEnum::CUTI_MIN_MASA_KERJA_BULAN) — cuma
     * relevan buat pengajuan Cuti, ditampilkan ke approver, tidak memblokir apa pun. */
    public function getTenureWarningProperty(): ?string
    {
        if ($this->record->type !== 'leave') {
            return null;
        }

        return app(AttendaceReqService::class)->getMinimumTenureWarning($this->record->user);
    }

    public function render()
    {
        return view('livewire.attendance-request-detail')
            ->layout('components.layout', ['title' => 'Detail Pengajuan']);
    }
}
