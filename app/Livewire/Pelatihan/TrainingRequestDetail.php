<?php

namespace App\Livewire\Pelatihan;

use App\Models\LMS\TrainingRequests;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
use App\Services\OrgHierarchyService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TrainingRequestDetail extends Component
{
    public TrainingRequests $record;

    public ?string $pendingAction = null;
    public ?string $note = null;

    public function mount(TrainingRequests $record): void
    {
        $record->load(['approvalSnapshot.activity', 'approvalHistories', 'user']);

        abort_unless($this->canAccess($record, Auth::user()), 403);

        $this->record = $record;
    }

    protected function canAccess(TrainingRequests $record, User $user): bool
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

        $actionMap = ['approve' => 'approved', 'reject' => 'rejected'];
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
            $this->record->load(['approvalSnapshot.activity', 'approvalHistories']);

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

    public function render()
    {
        return view('livewire.pelatihan.training-request-detail')
            ->layout('components.layout', ['title' => 'Detail Pengajuan Pelatihan']);
    }
}
