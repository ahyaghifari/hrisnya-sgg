<?php

namespace App\Livewire\Sikartap;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use App\Models\Sikartap\Sikartap;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Sikartap\SikartapDirectorDecisionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Keputusan Direktur massal — rewrite bulk_keputusan_direktur.php legacy (FIX21), dipicu dari
 * widget "Butuh Persetujuan Anda" kalau 1 pengaju (HRD) sekaligus mengajukan LEBIH DARI 1
 * karyawan ke Direktur (instruksi user 2026-08-25: "kalo 1 aja ya halaman biasa aja, tapi
 * jika lebih dari satu maka harus ada dan bisa pilih banyak sekalian" — biar widget tidak
 * numpuk 100 baris terpisah kalau ada pengangkatan massal). Kalau cuma 1 pengajuan pending,
 * widget langsung ke PengajuanDetailPage biasa — halaman ini TIDAK menggantikannya, murni
 * tambahan buat kasus banyak sekaligus.
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

    /** Semua pengajuan Sikartap yang lagi nunggu keputusan Direktur SAAT INI login (bukan
     * cuma 1 pengaju/submitter tertentu - kalau sudah masuk halaman ini, tampilkan semua yang
     * memang jadi tanggung jawab approver ini). */
    public function getPendingProperty()
    {
        $activities = app(ApprovalPermissionService::class)
            ->pendingApprovalsFor(Auth::user(), ApprovalModuleEnum::SIKARTAP_DIRECTOR_DECISION->value);

        $sikartapIds = $activities->pluck('snapshot.module_id')->filter()->values();

        return Sikartap::with('user.company')
            ->whereIn('id', $sikartapIds)
            ->whereHas('user')
            ->get();
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

    /** Baris diambil ULANG (bukan dari render sebelumnya) - kalau ada baris yang sudah
     * diputuskan orang lain sejak halaman dibuka, otomatis ke-skip lewat whereIn snapshot
     * status masih in_review (dicek di dalam service lewat approvalSnapshot relation),
     * bukan dicoba diproses dobel. */
    public function confirmDecision(): void
    {
        if ($this->confirmAction === 'reject') {
            $this->validate(['note' => ['required', 'string']]);
        }

        $service = app(SikartapDirectorDecisionService::class);
        $note = trim($this->note) !== '' ? $this->note : ($this->confirmAction === 'approve' ? 'Disetujui massal' : 'Ditolak massal');

        $targets = Sikartap::with('approvalSnapshot')->whereIn('id', $this->selected)->get();

        $success = 0;
        $failed = 0;

        foreach ($targets as $sikartap) {
            try {
                if (! $sikartap->approvalSnapshot || $sikartap->approvalSnapshot->status !== 'in_review') {
                    continue;
                }

                if ($this->confirmAction === 'approve') {
                    $service->approve($sikartap, $note);
                } else {
                    $service->reject($sikartap, $note);
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
