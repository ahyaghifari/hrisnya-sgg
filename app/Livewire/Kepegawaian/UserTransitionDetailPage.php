<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\UserTransitionTypeEnum;
use App\Helpers\PermissionHelper;
use App\Models\User\UserTransition;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
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
 */
#[Layout('components.layout', ['title' => 'Detail Perubahan Status Karyawan'])]
class UserTransitionDetailPage extends Component
{
    public UserTransition $record;

    public function mount(UserTransition $record): void
    {
        abort_unless(Auth::user()->can('View:UserTransition'), 403);

        if (! PermissionHelper::hasGlobalRole(Auth::user()) && $record->company_id !== Auth::user()->company_id) {
            abort(403);
        }

        app(UserTransitionService::class)->ensureApprovalSnapshot($record);

        $record->load(['user.profile.jobPosition', 'detail', 'attachments', 'approvalSnapshot.activity', 'approvalHistories']);

        $this->record = $record;
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
