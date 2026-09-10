<?php

namespace App\Livewire\Sikartap;

use App\Enum\CustomPermissionEnum;
use App\Models\Sikartap\Sikartap;
use App\Services\Sikartap\SikartapDirectorDecisionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pengesahan Direktur atas Ide Inovasi — massal. Pola sama DirekturBulkKeputusanPage. Tampil
 * pengajuan yang: company-nya mengaktifkan SikartapSettingEnum::IDE_INOVASI_PENGESAHAN_DIREKTUR,
 * atasan sudah meluluskan ide inovasinya, dan Direktur belum mengesahkan (status_direktur=pending).
 * Direktur di sini = pemegang jabatan Direktur (canDecide) atau HRD (View:Sikartap) yang juga
 * punya permission NILAI_IDE_INOVASI_DIREKTUR — sama gate yang dipakai PengajuanDetailPage.
 */
#[Layout('components.layout', ['title' => 'Pengesahan Ide Inovasi - Massal'])]
class DirekturBulkIdeInovasiPage extends Component
{
    /** @var array<int, string> ID Sikartap yang dicentang. */
    public array $selected = [];

    public bool $showConfirm = false;

    public string $confirmAction = 'approve';

    public string $note = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::NILAI_IDE_INOVASI_DIREKTUR->value), 403);
        abort_if($this->pending->isEmpty(), 404);
    }

    public function getPendingProperty()
    {
        $directorService = app(SikartapDirectorDecisionService::class);
        $user = Auth::user();

        return Sikartap::query()
            ->whereHas('user')
            ->whereHas('ideInovasi', fn ($q) => $q->where('status_atasan', 'approved')->where('status_direktur', 'pending'))
            ->with(['user.company', 'ideInovasi'])
            ->get()
            ->filter(fn (Sikartap $s) => $s->requiresIdeInovasiPengesahanDirektur()
                && ($user->can('View:Sikartap') || $directorService->canDecide($s, $user)))
            ->values();
    }

    public function toggleSelectAll(): void
    {
        $this->selected = count($this->selected) === $this->pending->count()
            ? []
            : $this->pending->pluck('id')->all();
    }

    public function getSelectedCountProperty(): int
    {
        return count($this->selected);
    }

    public function openConfirm(string $action): void
    {
        if ($this->selectedCount === 0) {
            return;
        }

        $this->confirmAction = $action;
        $this->note = '';
        $this->showConfirm = true;
    }

    public function closeConfirm(): void
    {
        $this->showConfirm = false;
    }

    public function confirmDecision(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::NILAI_IDE_INOVASI_DIREKTUR->value), 403);

        if ($this->confirmAction === 'reject') {
            $this->validate(['note' => ['required', 'string']]);
        }

        $status = $this->confirmAction === 'approve' ? 'approved' : 'rejected';
        $note = trim($this->note) !== '' ? $this->note : ($status === 'approved' ? 'Disahkan massal' : null);
        $actorId = Auth::id();

        // Diambil ULANG — baris yang sudah diputuskan sejak halaman dibuka otomatis ke-skip.
        $targets = $this->pending->whereIn('id', $this->selected);

        $done = 0;
        foreach ($targets as $sikartap) {
            if ($sikartap->ideInovasi?->status_direktur !== 'pending') {
                continue;
            }

            $sikartap->ideInovasi->recordTierDecision('direktur', $status, $actorId, $note);
            $done++;
        }

        $this->selected = [];
        $this->note = '';
        $this->showConfirm = false;
        unset($this->pending);

        Notification::make()->title("{$done} ide inovasi diproses")->success()->send();

        if ($this->pending->isEmpty()) {
            $this->redirect(route('sikartap.dashboard'));
        }
    }

    public function render()
    {
        return view('livewire.sikartap.direktur-bulk-ide-inovasi-page');
    }
}
