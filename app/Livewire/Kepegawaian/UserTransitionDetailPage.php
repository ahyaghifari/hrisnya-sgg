<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\UserTransitionTypeEnum;
use App\Helpers\PermissionHelper;
use App\Models\User;
use App\Models\User\UserTransition;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
use App\Services\OrgHierarchyService;
use App\Services\User\UserTransitionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail 1 pengajuan Perubahan Status Karyawan + approve/reject — padanan EditUserTransition
 * admin (bagian view + form action approve/reject), tapi bukan form edit penuh (field-nya
 * read-only di sini, sama seperti AttendanceRequestDetail). Logic approve/reject reuse
 * UserTransitionService (dipakai bareng admin).
 *
 * Gate akses: JADI APPROVER SAJA CUKUP, tidak perlu Shield permission View:UserTransition
 * terpisah (2026-08-09, dulu blanket can('View:UserTransition') blokir approver legit yang
 * kebetulan role-nya tidak di-assign permission itu) — pola disamakan dengan
 * AttendanceRequestDetail::canAccess().
 */
#[Layout('components.layout', ['title' => 'Detail Perubahan Status Karyawan'])]
class UserTransitionDetailPage extends Component
{
    public UserTransition $record;

    public function mount(UserTransition $record): void
    {
        app(UserTransitionService::class)->ensureApprovalSnapshot($record);

        $record->load(['user.profile.jobPosition', 'detail', 'attachments', 'approvalSnapshot.activity', 'approvalHistories']);

        abort_unless($this->canAccess($record, Auth::user()), 403);

        $this->record = $record;
    }

    protected function canAccess(UserTransition $record, User $user): bool
    {
        if ($record->created_by === $user->id || $record->user_id === $user->id) {
            return true;
        }

        $snapshot = $record->approvalSnapshot;

        if ($snapshot && app(ApprovalPermissionService::class)->hasPermission($snapshot->id, $user)) {
            return true;
        }

        if ($record->approvalHistories()->where('actor_id', $user->id)->exists()) {
            return true;
        }

        if (PermissionHelper::hasGlobalRole($user)) {
            return true;
        }

        if ($record->company_id !== $user->company_id) {
            return false;
        }

        return app(OrgHierarchyService::class)
            ->subordinates($user)
            ->whereKey($record->user_id)
            ->exists();
    }

    public function getTimelineProperty(): array
    {
        return app(ApprovalTimelineService::class)->build($this->record);
    }

    public function getCanApproveProperty(): bool
    {
        if (! $this->record->approvalSnapshot || $this->record->status !== 'pending') {
            return false;
        }

        return app(ApprovalPermissionService::class)->canApprove($this->record->approvalSnapshot->id, Auth::user());
    }

    public function approve(): void
    {
        abort_unless($this->canApprove, 403);

        try {
            app(UserTransitionService::class)->approve($this->record, Auth::id());

            $this->record->refresh();

            Notification::make()->title('Perubahan status karyawan berhasil disetujui')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Gagal menyetujui: ' . $e->getMessage())->danger()->send();
        }
    }

    public function reject(): void
    {
        abort_unless($this->canApprove, 403);

        try {
            app(UserTransitionService::class)->reject($this->record, Auth::id());

            $this->record->refresh();

            Notification::make()->title('Perubahan status karyawan ditolak')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Gagal menolak: ' . $e->getMessage())->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.kepegawaian.user-transition-detail-page');
    }
}
