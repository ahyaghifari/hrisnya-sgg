<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpReportDecision;
use App\Livewire\Sp\Concerns\HasReasonItems;
use App\Models\Sp\SpReport;
use App\Services\Sp\SpReportService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Detail Berita Acara alur baru - sisi HC (instruksi user 2026-10-01). Isi halaman mengikuti
 * status (lihat SpReportService); semua aturan transisi & otorisasi ada di service, komponen ini
 * hanya meneruskan dan menampilkan pesan validasinya.
 */
#[Layout('components.layout', ['title' => 'Detail Berita Acara'])]
class BeritaAcaraDetailPage extends Component
{
    use HasReasonItems;

    public SpReport $record;

    public ?string $nomor = null;
    public array $dasar_items = [['reason' => '', 'note' => '']];
    public ?string $kejadian = null;

    /** Draft yang sudah terisi tampil mode lihat; form baru muncul lewat tombol Edit. */
    public bool $editingDraft = false;

    /** Sama untuk Parafrase & Analisis: sudah tersimpan = mode lihat dulu. */
    public bool $editingAnalysis = false;

    public string $klarifikasi_karyawan = '';

    public ?string $analysis = null;
    public ?string $kesimpulan = null;
    public ?string $followup_plan = null;

    public function mount(SpReport $record): void
    {
        abort_if($record->isLegacy(), 404);

        $user = Auth::user();

        // HC (izin pembinaan + company sama), pembuat, atau "Mengetahui" yang ditunjuk (lihat saja).
        abort_unless(
            $user->id === $record->created_by
                || $user->id === $record->knows_by
                || ($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value) && $user->can('View:EmployeeSp') && $user->company_id === $record->company_id),
            403
        );

        $this->record = $record;
        $this->fillFromRecord();
        $this->editingDraft = ! filled($this->kejadian) || ! $this->record->dasarItems->isNotEmpty();
        $this->editingAnalysis = ! $this->hasSavedAnalysis();
    }

    /** Analisis dianggap sudah disimpan kalau ketiga kolom wajibnya terisi. */
    private function hasSavedAnalysis(): bool
    {
        return filled($this->record->analysis) && filled($this->record->kesimpulan) && filled($this->record->followup_plan);
    }

    public function editAnalysis(): void
    {
        $this->editingAnalysis = true;
    }

    public function cancelEditAnalysis(): void
    {
        $this->fillFromRecord();
        $this->editingAnalysis = false;
    }

    public function editDraft(): void
    {
        $this->editingDraft = true;
    }

    public function cancelEditDraft(): void
    {
        $this->fillFromRecord();
        $this->editingDraft = false;
    }

    private function fillFromRecord(): void
    {
        foreach (['nomor', 'kejadian', 'analysis', 'kesimpulan', 'followup_plan'] as $field) {
            $this->{$field} = $this->record->{$field};
        }

        $this->record->unsetRelation('dasarItems');
        $this->dasar_items = $this->reasonRowsFrom($this->record->dasarItems);
    }

    protected function reasonCompanyId(): ?string
    {
        return $this->record->company_id;
    }

    public function addDasarItem(): void
    {
        $this->dasar_items = $this->addReasonRow($this->dasar_items);
    }

    public function removeDasarItem(int $index): void
    {
        $this->dasar_items = $this->removeReasonRow($this->dasar_items, $index);
    }

    public function getCanManageProperty(): bool
    {
        $user = Auth::user();

        return $user->id === $this->record->created_by
            || ($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value) && $user->company_id === $this->record->company_id);
    }

    /** Peran yang sudah menandatangani, key = signer_role. */
    public function getSignaturesProperty()
    {
        return $this->record->document?->signatures->keyBy('signer_role') ?? collect();
    }

    /** Jalankan aksi service, tampilkan pesan validasi sebagai notifikasi, lalu muat ulang data. */
    private function run(callable $action, string $success): void
    {
        try {
            $action(app(SpReportService::class));
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }

        $this->record->refresh()->unsetRelation('document');
        $this->fillFromRecord();
        $this->editingDraft = false;
        $this->editingAnalysis = ! $this->hasSavedAnalysis();

        Notification::make()->title($success)->success()->send();
    }

    public function saveDraft(): void
    {
        $this->run(fn ($s) => $s->updateDraft($this->record, Auth::user(), [
            'nomor' => $this->nomor, 'dasar_items' => $this->reasonPayload($this->dasar_items), 'kejadian' => $this->kejadian,
        ]), 'Draft disimpan');
    }

    public function requestClarification(): void
    {
        $this->saveDraft();
        $this->run(fn ($s) => $s->requestClarification($this->record, Auth::user()), 'Lanjut ke klarifikasi');
    }

    public function fillClarification(): void
    {
        $this->run(fn ($s) => $s->fillClarificationByHc($this->record, Auth::user(), $this->klarifikasi_karyawan), 'Klarifikasi karyawan disimpan');
    }

    public function saveAnalysis(): void
    {
        $this->run(fn ($s) => $s->saveAnalysis($this->record, Auth::user(), $this->analysisFields()), 'Tersimpan');
    }

    /** Langkah FIX: isi dikunci. Tanda tangan baru muncul setelahnya, sebagai tombol terpisah. */
    public function sendForSigning(): void
    {
        $this->run(function ($s) {
            $s->saveAnalysis($this->record, Auth::user(), $this->analysisFields());
            $s->sendForSigning($this->record, Auth::user());
        }, 'Berita Acara di-FIX, isi terkunci. Silakan tanda tangani.');
    }

    /** Modal tanda tangan selesai - muat ulang status BA (bisa jadi SELESAI) & daftar tanda tangan. */
    #[On('document-signed')]
    public function refreshAfterSign(): void
    {
        $this->record->refresh()->unsetRelation('document');
        unset($this->canSignNow, $this->signatures);
    }

    /** Anda sedang ditugaskan menandatangani BA ini (pembuat = yang pertama). */
    public function getCanSignNowProperty(): bool
    {
        $document = $this->record->document?->loadMissing(['documentable', 'signatures']);

        return $document?->pendingRoleFor(Auth::user()) !== null;
    }

    public function decide(string $decision): void
    {
        $this->run(fn ($s) => $s->decide($this->record, Auth::user(), SpReportDecision::from($decision)), 'Keputusan disimpan');
    }

    /** @return array<string, ?string> */
    private function analysisFields(): array
    {
        return [
            'analysis' => $this->analysis,
            'kesimpulan' => $this->kesimpulan,
            'followup_plan' => $this->followup_plan,
        ];
    }

    public function render()
    {
        return view('livewire.sp.berita-acara-detail-page');
    }
}
