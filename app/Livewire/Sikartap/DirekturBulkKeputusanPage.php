<?php

namespace App\Livewire\Sikartap;

use App\Models\Sikartap\Sikartap;
use App\Services\Sikartap\SikartapDirectorDecisionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Keputusan Direktur massal — pilih banyak pengajuan Sikartap yang statusnya 'diajukan' dan
 * Direktur yang login memang ditunjuk memutuskannya (Setting sikartap_direktur_job_position_id).
 * Lepas Approval Engine (2026-09-07): keputusan lewat SikartapDirectorDecisionService langsung
 * nulis kolom keputusan_direktur_* + markUserPermanent, bukan advanceStep snapshot.
 */
#[Layout('components.layout', ['title' => 'Keputusan Direktur - Massal'])]
class DirekturBulkKeputusanPage extends Component
{
    /** @var array<int, string> ID Sikartap yang dicentang. */
    public array $selected = [];

    public bool $showConfirm = false;

    public string $confirmAction = 'approve';

    public string $note = '';

    public function mount(): void
    {
        abort_if($this->pending->isEmpty(), 404);
    }

    /** Semua pengajuan Sikartap status 'diajukan' yang Direktur login boleh putuskan. */
    public function getPendingProperty()
    {
        $service = app(SikartapDirectorDecisionService::class);

        return Sikartap::query()
            ->where('status_keputusan_direktur', 'diajukan')
            ->whereHas('user')
            ->with('user.company')
            ->get()
            ->filter(fn (Sikartap $s) => $service->canDecide($s, Auth::user()))
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
        if ($this->confirmAction === 'reject') {
            $this->validate(['note' => ['required', 'string']]);
        }

        $service = app(SikartapDirectorDecisionService::class);
        $note = trim($this->note) !== '' ? $this->note : ($this->confirmAction === 'approve' ? 'Disetujui massal' : 'Ditolak massal');
        $actor = Auth::user();

        $success = 0;
        $failed = 0;

        // Diambil ULANG — baris yang sudah diputuskan orang lain sejak halaman dibuka otomatis
        // ke-skip (status bukan 'diajukan' lagi -> service melempar, masuk catch).
        $targets = Sikartap::whereIn('id', $this->selected)
            ->where('status_keputusan_direktur', 'diajukan')
            ->get();

        foreach ($targets as $sikartap) {
            try {
                if ($this->confirmAction === 'approve') {
                    $service->approve($sikartap, $actor, $note);
                } else {
                    $service->reject($sikartap, $actor, $note);
                }

                $success++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        $this->selected = [];
        $this->note = '';
        $this->showConfirm = false;

        Notification::make()
            ->title($failed > 0 ? "{$success} diproses, {$failed} gagal" : "{$success} pengajuan berhasil diproses")
            ->{$failed > 0 ? 'warning' : 'success'}()
            ->send();

        if ($this->pending->isEmpty()) {
            $this->redirect(route('sikartap.dashboard'));
        }
    }

    public function render()
    {
        return view('livewire.sikartap.direktur-bulk-keputusan-page');
    }
}
