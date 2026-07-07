<?php

namespace App\Livewire;

use App\Filament\Resources\AttendanceRequests\Schemas\AttendanceRequestForm;
use App\Models\Attendance\AttendanceRequests;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
use App\Services\OrgHierarchyService;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AttendanceRequestDetail extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public AttendanceRequests $record;

    public ?string $pendingAction = null;
    public ?string $note = null;

    public function mount(AttendanceRequests $record): void
    {
        $record->load(['approvalSnapshot.activity', 'approvalHistories']);

        abort_unless($this->canAccess($record, Auth::user()), 403);

        $this->record = $record;

        // PWA redesign tidak pakai Filament schema sama sekali (lihat render()) — skip
        // fill() di sini supaya Filament tidak perlu di-build untuk halaman PWA.
        if (! request()->routeIs('pwa.*')) {
            // Sebagian schema (Tabs Riwayat Approval) pakai Repeater ->relationship(),
            // itu komponen form biasa (bukan infolist) jadi butuh fill() manual supaya
            // data relasinya ke-hydrate — beda dari TextEntry yang otomatis baca record.
            $this->getSchema('infolist')->fill($record->attributesToArray());
        }
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
            $this->record->load(['approvalSnapshot.activity', 'approvalHistories']);

            if (! request()->routeIs('pwa.*')) {
                $this->getSchema('infolist')->fill($this->record->attributesToArray());
            }

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

    public function infolist(Schema $schema): Schema
    {
        return AttendanceRequestForm::configure($schema->record($this->record));
    }

    public function render()
    {
        $isPwa = request()->routeIs('pwa.*');

        return view($isPwa ? 'livewire.pwa.pengajuan-detail' : 'livewire.attendance-request-detail')
            ->layout($isPwa ? 'components.pwa-layout' : 'components.layout', ['title' => 'Detail Pengajuan']);
    }
}
