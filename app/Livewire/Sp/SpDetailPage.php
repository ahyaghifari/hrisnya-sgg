<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Models\Sp\EmployeeSp;
use App\Models\User;
use App\Services\Sp\SpService;
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
 *
 * Field inti SP (nomor, Berlaku = start_date/end_date, Mengetahui) juga dibuka diedit (instruksi
 * user 2026-09-25) - prefix `sp_` biar tidak bentrok nama property dengan `nomor` milik Berita
 * Acara (sp_reports.nomor, field terpisah).
 *
 * DIKUNCI setelah SEMUA penanda tangan yang diharapkan menandatangani (instruksi user
 * 2026-09-28, menutup gap "dokumen bisa diedit walau sudah ditandatangani" -
 * Document::isFullySigned()) - sebelum knows_by konfirmasi sendiri, HC masih boleh koreksi
 * salah ketik; begitu knows_by sudah menandatangani, isi surat (nomor/tanggal/Mengetahui/poin
 * pelanggaran/Berita Acara) dikunci semua - itu jaminan yang mau diberikan TTE-nya (kalau tetap
 * bisa diedit diam-diam setelah tanda tangan, hash pembanding di halaman verifikasi jadi
 * percuma).
 */
#[Layout('components.layout', ['title' => 'Detail Surat Peringatan'])]
class SpDetailPage extends Component
{
    public EmployeeSp $record;

    public bool $editingSp = false;

    public ?string $sp_nomor = null;
    public ?string $sp_start_date = null;
    public ?string $sp_end_date = null;
    public ?string $sp_knows_by = null;

    public bool $cancellingSp = false;

    public string $cancel_reason = '';

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

        abort_unless($record->canBeViewedBy($user), 403);

        $record->load(['user', 'items', 'report', 'createdBy', 'knowsBy', 'coaching', 'document.signatures']);

        $this->record = $record;
    }

    /** Semua penanda tangan yang diharapkan (created_by + knows_by) sudah menandatangani -
     *  dipakai gerbang server-side DAN toggle tampilan tombol Edit di blade. */
    public function getIsFullySignedProperty(): bool
    {
        return $this->record->document?->isFullySigned() ?? false;
    }

    /** HC beneran (bukan cuma "Mengetahui" yang ikut boleh LIHAT lewat canBeViewedBy()) - dipakai
     *  gerbang server-side tiap aksi tulis/batal DAN toggle tombol Edit/Batalkan di blade.
     *  "Mengetahui" cuma boleh baca isi surat, sama sekali tidak boleh mengubahnya. */
    public function getCanManageProperty(): bool
    {
        $user = Auth::user();

        return $user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value) && $user->can('View:EmployeeSp');
    }

    public function editSp(): void
    {
        abort_unless($this->canManage, 403);
        abort_if($this->isFullySigned, 403, 'Dokumen sudah ditandatangani lengkap, tidak bisa diedit lagi.');

        $this->sp_nomor = $this->record->nomor;
        $this->sp_start_date = $this->record->start_date?->toDateString();
        $this->sp_end_date = $this->record->end_date?->toDateString();
        $this->sp_knows_by = $this->record->knows_by;
        $this->editingSp = true;
    }

    public function getKnowsByOptionsProperty()
    {
        return User::where('company_id', $this->record->company_id)->orderBy('name')->pluck('name', 'id');
    }

    public function saveSp(): void
    {
        abort_unless($this->canManage, 403);
        abort_if($this->isFullySigned, 403, 'Dokumen sudah ditandatangani lengkap, tidak bisa diedit lagi.');

        $this->validate([
            'sp_nomor' => ['required', 'string', 'max:255'],
            'sp_start_date' => ['required', 'date'],
            'sp_end_date' => ['nullable', 'date', 'after_or_equal:sp_start_date'],
            'sp_knows_by' => ['required', 'exists:users,id'],
        ]);

        $this->record->update([
            'nomor' => $this->sp_nomor,
            'start_date' => $this->sp_start_date,
            'end_date' => $this->sp_end_date,
            'knows_by' => $this->sp_knows_by,
        ]);

        $this->record->load('knowsBy');
        $this->editingSp = false;
    }

    public function cancelEditSp(): void
    {
        $this->editingSp = false;
    }

    public function openCancelSp(): void
    {
        abort_unless($this->canManage, 403);

        $this->cancel_reason = '';
        $this->cancellingSp = true;
    }

    public function closeCancelSp(): void
    {
        $this->cancellingSp = false;
    }

    public function confirmCancelSp(): void
    {
        abort_unless($this->canManage, 403);

        $this->validate(['cancel_reason' => ['required', 'string', 'max:1000']], [], ['cancel_reason' => 'Alasan pembatalan']);

        app(SpService::class)->cancel($this->record, $this->cancel_reason, Auth::id());

        $this->record->refresh()->load('cancelledBy');
        $this->cancellingSp = false;
    }

    public function editItems(): void
    {
        abort_unless($this->canManage, 403);
        abort_if($this->isFullySigned, 403, 'Dokumen sudah ditandatangani lengkap, tidak bisa diedit lagi.');

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
        abort_unless($this->canManage, 403);
        abort_if($this->isFullySigned, 403, 'Dokumen sudah ditandatangani lengkap, tidak bisa diedit lagi.');

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
        // Dikunci ikut status SP-nya (bukan report->document sendiri) - Berita Acara yang
        // ditambahkan BELAKANGAN (setelah SP terbit) belum tentu punya baris document/signature
        // sendiri (hook tanda tangan cuma jalan sekali saat SP terbit), jadi patokan yang aman
        // dan konsisten adalah status SP induknya.
        abort_unless($this->canManage, 403);
        abort_if($this->isFullySigned, 403, 'Dokumen sudah ditandatangani lengkap, tidak bisa diedit lagi.');

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
        abort_unless($this->canManage, 403);
        abort_if($this->isFullySigned, 403, 'Dokumen sudah ditandatangani lengkap, tidak bisa diedit lagi.');

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
