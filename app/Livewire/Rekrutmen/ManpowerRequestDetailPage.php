<?php

namespace App\Livewire\Rekrutmen;

use App\Enum\JobPositionReqStatus;
use App\Models\Job\ManpowerRequests;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
use App\Services\JobPosition\ManPowerReqService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail Permintaan Tenaga Kerja + approve/reject — pola persis UserTransitionDetailPage.
 * Beda dari admin (EditManpowerRequest): admin cuma punya approve, di sini juga ada reject
 * (paritas dengan modul approval lain — lihat catatan planning/eager-pondering-turtle.md).
 */
#[Layout('components.layout', ['title' => 'Detail Permintaan Tenaga Kerja'])]
class ManpowerRequestDetailPage extends Component
{
    public ManpowerRequests $record;
    public string $note = '';

    public function mount(ManpowerRequests $record): void
    {
        abort_unless(Auth::user()->can('View:ManpowerRequests'), 403);

        abort_unless(
            Auth::user()->canFilterByCompany() || $record->company_id === Auth::user()->company_id,
            403
        );

        app(ManPowerReqService::class)->ensureApprovalSnapshot($record);

        $record->load(['company', 'jobPosition.orgStructure', 'user', 'approvalSnapshot.activity', 'approvalHistories']);

        $this->record = $record;
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

            $this->record->refresh();

            Notification::make()->title('Permintaan berhasil disetujui')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Gagal menyetujui: ' . $e->getMessage())->danger()->send();
        }
    }

    public function reject(): void
    {
        abort_unless($this->canApprove, 403);

        try {
            app(ManPowerReqService::class)->reject($this->record, $this->note, Auth::id());

            $this->record->refresh();

            Notification::make()->title('Permintaan ditolak')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Gagal menolak: ' . $e->getMessage())->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.rekrutmen.manpower-request-detail-page');
    }
}
