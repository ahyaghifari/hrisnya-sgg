<?php

namespace App\Livewire\Sarpras;

use App\Models\Sarpras\SarprasRequest;
use App\Models\Sarpras\SarprasRequestItem;
use App\Models\User;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
use App\Services\Sarpras\SarprasRequestService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Halaman detail + keputusan Sarana & Prasarana — satu halaman menangani 3 peran (Kabid
 * teruskan, Direktur putuskan per item, Unit tandai selesai), pola sama
 * PengajuanDetailPage.php (Sikartap) & AttendanceRequestDetail.php. Tombol yang muncul
 * ditentukan dari $request->status + ApprovalPermissionService::canApprove() — BUKAN dari
 * approver_type/step order Approval Engine (itu generic/dikonfigurasi admin, business rule
 * "siapa boleh ngapain di status apa" murni domain Sarpras, porting aksi_tersedia() sumber).
 */
#[Layout('components.layout', ['title' => 'Detail Permohonan Sarana & Prasarana'])]
class SarprasRequestDetailPage extends Component
{
    public SarprasRequest $sarprasRequest;

    public string $catatanKabid = '';

    /** @var array<string, array{keputusan: string, jumlah_disetujui: ?int, catatan: ?string}> */
    public array $itemDecisions = [];

    public bool $confirmingSelesai = false;

    public function mount(SarprasRequest $sarprasRequest): void
    {
        $sarprasRequest->load(['items', 'attachments', 'signatures', 'approvalSnapshot.activity', 'user']);

        abort_unless($this->canAccess($sarprasRequest, Auth::user()), 403);

        $this->sarprasRequest = $sarprasRequest;

        foreach ($sarprasRequest->items as $item) {
            $this->itemDecisions[$item->id] = [
                'keputusan' => 'disetujui',
                'jumlah_disetujui' => $item->jumlah,
                'catatan' => null,
            ];
        }
    }

    protected function canAccess(SarprasRequest $request, User $user): bool
    {
        if ($request->user_id === $user->id) {
            return true;
        }

        $snapshot = $request->approvalSnapshot;

        if ($snapshot && app(ApprovalPermissionService::class)->hasPermission($snapshot->id, $user)) {
            return true;
        }

        return $request->approvalHistories()->where('actor_id', $user->id)->exists();
    }

    public function getCanApproveProperty(): bool
    {
        if (! $this->sarprasRequest->approvalSnapshot) {
            return false;
        }

        return app(ApprovalPermissionService::class)
            ->canApprove($this->sarprasRequest->approvalSnapshot->id, Auth::user());
    }

    public function getCanTeruskanProperty(): bool
    {
        return $this->sarprasRequest->status === 'diajukan' && $this->canApprove;
    }

    public function getCanPutuskanProperty(): bool
    {
        return in_array($this->sarprasRequest->status, ['menunggu_direktur', 'diputuskan_sebagian'], true)
            && $this->canApprove;
    }

    public function getCanSelesaikanProperty(): bool
    {
        return in_array($this->sarprasRequest->status, ['disetujui', 'disetujui_sebagian'], true)
            && Auth::id() === $this->sarprasRequest->user_id;
    }

    public function teruskan(): void
    {
        try {
            app(SarprasRequestService::class)->teruskan($this->sarprasRequest, Auth::user(), $this->catatanKabid ?: null);

            $this->refreshRecord();
            Notification::make()->title('Permohonan diteruskan ke Direktur')->success()->send();
        } catch (RuntimeException $e) {
            Notification::make()->title('Gagal meneruskan')->body($e->getMessage())->danger()->send();
        }
    }

    public function putuskanItem(string $itemId): void
    {
        $decision = $this->itemDecisions[$itemId] ?? null;

        if (! $decision) {
            return;
        }

        /** @var SarprasRequestItem $item */
        $item = $this->sarprasRequest->items->firstWhere('id', $itemId);

        if (! $item) {
            return;
        }

        try {
            app(SarprasRequestService::class)->putuskanItem(
                $item,
                $decision['keputusan'],
                $decision['keputusan'] === 'disetujui' ? (int) $decision['jumlah_disetujui'] : null,
                $decision['catatan'] ?: null,
                Auth::user(),
            );

            $this->refreshRecord();
            Notification::make()->title('Item berhasil diputuskan')->success()->send();
        } catch (RuntimeException $e) {
            Notification::make()->title('Gagal memutuskan item')->body($e->getMessage())->danger()->send();
        }
    }

    public function selesaikan(): void
    {
        try {
            app(SarprasRequestService::class)->selesaikan($this->sarprasRequest, Auth::user());

            $this->confirmingSelesai = false;
            $this->refreshRecord();
            Notification::make()->title('Permohonan ditandai selesai')->success()->send();
        } catch (RuntimeException $e) {
            Notification::make()->title('Gagal menandai selesai')->body($e->getMessage())->danger()->send();
        }
    }

    public function getTimelineProperty(): array
    {
        return app(ApprovalTimelineService::class)->build($this->sarprasRequest);
    }

    public function getAttachmentUrl(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    private function refreshRecord(): void
    {
        $this->sarprasRequest->refresh();
        $this->sarprasRequest->load(['items', 'attachments', 'signatures', 'approvalSnapshot.activity']);
    }

    public function render()
    {
        return view('livewire.sarpras.sarpras-request-detail-page');
    }
}
