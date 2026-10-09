<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Sp\Concerns\HasReasonItems;
use App\Models\Sp\EmployeeSp;
use App\Models\Sp\SpReason;
use App\Models\User;
use App\Services\Sp\SpService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Illuminate\Validation\ValidationException;
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
 * DIKUNCI begitu PEMBUAT menandatangani (instruksi user 2026-10-03; sebelumnya baru setelah semua
 * pihak, 2026-09-28) - isi surat (nomor/tanggal/Mengetahui/poin pelanggaran/Berita Acara) tidak
 * boleh berubah setelah ada tanda tangan, itu jaminan yang mau diberikan TTE-nya (kalau tetap
 * bisa diedit diam-diam, hash pembanding di halaman verifikasi jadi percuma). SP draft (belum
 * ada dokumen/tanda tangan) bebas diedit.
 */
#[Layout('components.layout', ['title' => 'Detail Surat Peringatan'])]
class SpDetailPage extends Component
{
    use HasReasonItems;

    public EmployeeSp $record;

    public bool $editingSp = false;

    public ?string $sp_nomor = null;
    public ?string $sp_start_date = null;
    public ?string $sp_end_date = null;
    public ?string $sp_knows_by = null;

    public bool $cancellingSp = false;

    public string $cancel_reason = '';

    /** Tanda Tangan &amp; Terbitkan (instruksi user 2026-09-29) - SP dari CreateSpPage cuma
     *  disimpan sebagai draft, belum ada Document/tanda tangan. Cuma pembuatnya sendiri (bukan HC
     *  lain) yang boleh menerbitkan, sama seperti konsen eksplisit created_by di TTE Fase 1
     *  sebelumnya - password diminta lagi di sini sebagai bukti konsen itu. */
    public bool $confirmingIssue = false;

    public ?string $issuePassword = null;

    public bool $editingItems = false;

    /** @var array<int, array{reason: string, note: string}> */
    public array $items = [];

    public bool $editingReport = false;

    public ?string $nomor = null;
    public ?string $kejadian = null;
    /** @var array<int, array{reason: string, note: string}> */
    public array $dasar_items = [['reason' => '', 'note' => '']];
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

    /** Dikunci begitu PEMBUAT sudah menandatangani (instruksi user 2026-10-03) - sebelumnya baru
     *  setelah semua pihak. Dipakai gerbang server-side DAN toggle tampilan tombol Edit di blade. */
    public function getIsLockedProperty(): bool
    {
        $document = $this->record->document;

        return $document !== null
            && ($document->signatures->contains('signer_role', 'created_by') || $document->isFullySigned());
    }

    /** Draft = belum ada baris registry `documents` sama sekali - dikenali dari ini, tidak perlu
     *  kolom status baru (SP terbit selalu langsung dapat Document lewat SpService::afterIssued()). */
    public function getIsDraftProperty(): bool
    {
        return ! $this->record->document;
    }

    /** SP terbit yang sedang direvisi: dokumen ada tapi kuncinya dibuka, menunggu pembuat menerbitkan ulang. */
    public function getIsAwaitingReissueProperty(): bool
    {
        return $this->record->document !== null && $this->record->document->fixed_at === null;
    }

    /** HC boleh merevisi SP yang sudah terkunci selama belum semua pihak menandatangani. */
    public function getCanReviseProperty(): bool
    {
        $document = $this->record->document;

        return $this->canManage && $document !== null && $document->fixed_at !== null
            && ! $this->record->isCancelled() && ! $document->isFullySigned();
    }

    public string $revision_reason = '';

    public function revise(): void
    {
        abort_unless($this->canRevise, 403);

        try {
            app(SpService::class)->revise($this->record, Auth::user(), trim($this->revision_reason));
        } catch (ValidationException $e) {
            $this->addError('revision_reason', collect($e->errors())->flatten()->first());

            return;
        }

        $this->revision_reason = '';
        $this->record->refresh()->load(['document.signatures', 'report.document.signatures']);
        Notification::make()->title('Surat Peringatan dibuka untuk revisi, tanda tangan sebelumnya dibatalkan')->success()->send();
    }

    /** Cuma pembuat SP sendiri yang boleh menandatangani & menerbitkan draftnya (instruksi user
     *  2026-09-29) - bukan HC lain yang kebetulan punya izin sama. */
    public function getCanIssueProperty(): bool
    {
        return ($this->isDraft || $this->isAwaitingReissue) && ! $this->record->isCancelled() && Auth::id() === $this->record->created_by;
    }

    /** HC beneran (bukan cuma "Mengetahui" yang ikut boleh LIHAT lewat canBeViewedBy()) - dipakai
     *  gerbang server-side tiap aksi tulis/batal DAN toggle tombol Edit/Batalkan di blade.
     *  "Mengetahui" cuma boleh baca isi surat, sama sekali tidak boleh mengubahnya. */
    public function getCanManageProperty(): bool
    {
        $user = Auth::user();

        return $user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value) && $user->can('View:EmployeeSp');
    }

    public function openConfirmIssue(): void
    {
        abort_unless($this->canIssue, 403);

        $this->issuePassword = null;
        $this->resetErrorBag();
        $this->confirmingIssue = true;
    }

    public function cancelConfirmIssue(): void
    {
        $this->confirmingIssue = false;
        $this->issuePassword = null;
    }

    /** Password sendiri sebagai bukti konsen eksplisit created_by (Pasal 59 ayat 3 butir 6 PP
     *  71/2019), BARU benar-benar terbit: Document dibuat, ditandatangani created_by, knows_by
     *  dinotif, merit point diterapkan, karyawan dinotif SP (semua di SpService::afterIssued()). */
    public function issue(): void
    {
        abort_unless($this->canIssue, 403);

        $this->validate(['issuePassword' => ['required', 'string']], [], ['issuePassword' => 'Password']);

        if (! Hash::check($this->issuePassword, Auth::user()->password)) {
            $this->addError('issuePassword', 'Password salah.');

            return;
        }

        $this->isAwaitingReissue
            ? app(SpService::class)->reissue($this->record)
            : app(SpService::class)->afterIssued($this->record);

        $this->record->refresh()->load(['document.signatures', 'report.document.signatures']);
        $this->confirmingIssue = false;
        $this->issuePassword = null;
    }

    public function editSp(): void
    {
        abort_unless($this->canManage, 403);
        abort_if($this->isLocked, 403, 'Dokumen sudah ditandatangani pembuat, tidak bisa diedit lagi.');

        $this->sp_nomor = $this->record->nomor;
        $this->sp_start_date = $this->record->start_date?->toDateString();
        $this->sp_end_date = $this->record->end_date?->toDateString();
        $this->sp_knows_by = $this->record->knows_by;
        $this->editingSp = true;
    }

    public function getKnowsByOptionsProperty()
    {
        return User::whereCompany($this->record->company_id)->atDepartmentLevel()->orderByName()->get()->pluck('name', 'id');
    }

    public function saveSp(): void
    {
        abort_unless($this->canManage, 403);
        abort_if($this->isLocked, 403, 'Dokumen sudah ditandatangani pembuat, tidak bisa diedit lagi.');

        $this->validate([
            'sp_nomor' => ['required', 'string', 'max:255'],
            'sp_start_date' => ['required', 'date'],
            'sp_end_date' => ['nullable', 'date', 'after_or_equal:sp_start_date'],
            'sp_knows_by' => ['required', 'exists:users,id'],
        ]);
        abort_unless($this->knowsByOptions->has($this->sp_knows_by), 403);

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
        abort_if($this->isLocked, 403, 'Dokumen sudah ditandatangani pembuat, tidak bisa diedit lagi.');

        $this->items = $this->reasonRowsFrom($this->record->items);
        $this->editingItems = true;
    }

    protected function reasonLevel(): ?string
    {
        return $this->record->sp_level->value;
    }

    protected function reasonCompanyId(): ?string
    {
        return $this->record->company_id;
    }

    /** Pelanggaran sama dengan SP lain karyawan ini yang aktif & terbit (rantai SP) - kecuali SP ini sendiri. */
    public function getRepeatOptionsProperty(): array
    {
        $options = [];

        foreach (EmployeeSp::active()->issued()->where('user_id', $this->record->user_id)->whereKeyNot($this->record->id)->with('items.reason')->orderBy('start_date')->get() as $sp) {
            foreach ($sp->items->whereNotNull('reason_id') as $item) {
                $options["repeat:{$sp->id}:{$item->reason_id}"] = "{$item->reason?->name} - berulang dari {$sp->sp_level->getLabel()} ({$sp->nomor})";
            }
        }

        return $options;
    }

    public function addItem(): void
    {
        $this->items = $this->addReasonRow($this->items);
    }

    public function removeItem(int $index): void
    {
        $this->items = $this->removeReasonRow($this->items, $index);
    }

    public function saveItems(): void
    {
        abort_unless($this->canManage, 403);
        abort_if($this->isLocked, 403, 'Dokumen sudah ditandatangani pembuat, tidak bisa diedit lagi.');

        $rows = array_values(array_filter($this->reasonPayload($this->items), fn ($row) => filled($row['reason_id']) || filled($row['note'])));

        if ($rows === []) {
            $this->addError('items', 'Minimal 1 pelanggaran.');

            return;
        }

        foreach ($rows as $row) {
            abort_unless(
                (! $row['reason_id'] || ($row['repeat_sp_id']
                    ? isset($this->repeatOptions["repeat:{$row['repeat_sp_id']}:{$row['reason_id']}"])
                    : $this->reasonOptions->has($row['reason_id']))),
                403
            );
        }

        $this->record->items()->delete();

        foreach ($rows as $index => $row) {
            $this->record->items()->create(SpReason::composeItem($row) + ['sort_order' => $index + 1]);
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
        abort_if($this->isLocked, 403, 'Dokumen sudah ditandatangani pembuat, tidak bisa diedit lagi.');

        $this->nomor = $this->record?->report?->nomor;
        $this->kejadian = $this->record->report?->kejadian;
        $this->dasar_items = $this->reasonRowsFrom($this->record->report?->dasarItems ?? []);
        $this->subject_clarification = $this->record->report?->subject_clarification;
        $this->analysis = $this->record->report?->analysis;
        $this->kesimpulan = $this->record->report?->kesimpulan;
        $this->followup_plan = $this->record->report?->followup_plan;
        $this->editingReport = true;
    }

    public function saveReport(): void
    {
        abort_unless($this->canManage, 403);
        abort_if($this->isLocked, 403, 'Dokumen sudah ditandatangani pembuat, tidak bisa diedit lagi.');

        $report = $this->record->report()->updateOrCreate([], [
            'nomor' => $this->nomor,
            'kejadian' => $this->kejadian,
            'subject_clarification' => $this->subject_clarification,
            'analysis' => $this->analysis,
            'kesimpulan' => $this->kesimpulan,
            'followup_plan' => $this->followup_plan,
        ]);
        $report->syncDasarItems($this->reasonPayload($this->dasar_items));

        $this->record->load('report');
        $this->editingReport = false;
    }

    public function addDasarItem(): void
    {
        $this->dasar_items = $this->addReasonRow($this->dasar_items);
    }

    public function removeDasarItem(int $index): void
    {
        $this->dasar_items = $this->removeReasonRow($this->dasar_items, $index);
    }

    /** Modal tanda tangan selesai - muat ulang dokumen & tanda tangannya (bisa jadi terkunci). */
    #[On('document-signed')]
    public function refreshAfterSign(): void
    {
        $this->record->refresh()->unsetRelation('document');
        $this->record->load('document.signatures');
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
