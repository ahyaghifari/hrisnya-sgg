<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Helpers\PermissionHelper;
use App\Models\Sp\EmployeeSp;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail SP — sisi HC. Padanan CoachingDetailPage. Restrukturisasi 2026-09-25: tampilkan
 * sp_items (poin pelanggaran) + sp_reports (Berita Acara, internal HR).
 *
 * Uraian/kronologi dibuka buat diedit di portal juga (instruksi user 2026-09-24, selaras dengan
 * EmployeeSpResource\Pages\EditEmployeeSp yang juga sudah dibuka) — field SP lain tetap final.
 * Berita Acara juga dibuka diedit bebas kapan saja setelah SP terbit (instruksi user 2026-09-24)
 * - beda dari items, report belum tentu ada saat SP dibuat (opsional), makanya save-nya
 * updateOrCreate.
 */
#[Layout('components.layout', ['title' => 'Detail Surat Peringatan'])]
class SpDetailPage extends Component
{
    public EmployeeSp $record;

    public bool $editingItems = false;

    /** @var array<int, string> */
    public array $items = [];

    public bool $editingReport = false;

    public ?string $nomor = null;
    public ?string $kejadian = null;
    public ?string $dasar_klarifikasi = null;
    public ?string $subject_clarification = null;
    public ?string $analysis = null;
    public ?string $kesimpulan = null;
    public ?string $followup_plan = null;

    public function mount(EmployeeSp $record): void
    {
        $user = Auth::user();

        abort_unless($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);
        abort_unless($user->can('View:EmployeeSp'), 403);
        abort_unless($record->company_id === $user->company_id || PermissionHelper::hasGlobalRole($user), 403);

        $record->load(['user', 'items', 'report', 'createdBy', 'knowsBy', 'coaching']);

        $this->record = $record;
    }

    public function editItems(): void
    {
        $this->items = $this->record->items->pluck('text')->all();
        $this->editingItems = true;
    }

    public function addItem(): void
    {
        $this->items[] = '';
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function saveItems(): void
    {
        $texts = array_values(array_filter(array_map('trim', $this->items), fn ($text) => $text !== ''));

        if ($texts === []) {
            $this->addError('items', 'Minimal 1 uraian.');

            return;
        }

        $this->record->items()->delete();

        foreach ($texts as $index => $text) {
            $this->record->items()->create(['text' => $text, 'sort_order' => $index + 1]);
        }

        $this->record->load('items');
        $this->editingItems = false;
    }

    public function cancelEditItems(): void
    {
        $this->editingItems = false;
    }

    public function editReport(): void
    {
        $this->nomor = $this->record?->report?->nomor;
        $this->kejadian = $this->record->report?->kejadian;
        $this->dasar_klarifikasi = $this->record->report?->dasar_klarifikasi;
        $this->subject_clarification = $this->record->report?->subject_clarification;
        $this->analysis = $this->record->report?->analysis;
        $this->kesimpulan = $this->record->report?->kesimpulan;
        $this->followup_plan = $this->record->report?->followup_plan;
        $this->editingReport = true;
    }

    public function saveReport(): void
    {
        $this->record->report()->updateOrCreate([], [
            'nomor' => $this->nomor,
            'kejadian' => $this->kejadian,
            'dasar_klarifikasi' => $this->dasar_klarifikasi,
            'subject_clarification' => $this->subject_clarification,
            'analysis' => $this->analysis,
            'kesimpulan' => $this->kesimpulan,
            'followup_plan' => $this->followup_plan,
        ]);

        $this->record->load('report');
        $this->editingReport = false;
    }

    public function cancelEditReport(): void
    {
        $this->editingReport = false;
    }

    public function render()
    {
        return view('livewire.sp.sp-detail-page');
    }
}
