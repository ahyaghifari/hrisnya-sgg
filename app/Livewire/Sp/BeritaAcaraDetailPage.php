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

    use \Livewire\WithFileUploads;

    public SpReport $record;

    /** Berkas Berita Acara bertanda tangan yang akan diunggah. */
    public $signedFile = null;

    public ?string $nomor = null;
    public ?string $coaching_id = null;
    public array $dasar_items = [['reason' => '', 'note' => '']];
    public ?string $kejadian = null;

    public ?string $subject_clarification = null;

    public ?string $analysis = null;
    public ?string $kesimpulan = null;
    public ?string $followup_plan = null;

    public function mount(SpReport $record): void
    {
        abort_if($record->isLegacy(), 404);

        $user = Auth::user();

        // HC (izin pembinaan + company sama), pembuat, atau "Mengetahui" / atasan langsung yang ditunjuk (lihat & tanda tangan).
        abort_unless(
            $user->id === $record->created_by
                || $user->id === $record->knows_by
                || $user->id === $record->supervisor_id
                || ($user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value) && $user->can('View:EmployeeSp') && $user->company_id === $record->company_id),
            403
        );

        $this->record = $record;
        $this->fillFromRecord();
    }

    public function getCoachingOptionsProperty()
    {
        return \App\Models\Coaching\Coaching::where('user_id', $this->record->user_id)->latest()->get()
            ->mapWithKeys(fn ($c) => [$c->id => "{$c->title} ({$c->created_at->translatedFormat('d M Y')})"]);
    }

    /** Atasan langsung karyawan (sebelum FIX dihitung langsung, sesudahnya yang tercatat). */
    public function getSupervisorProperty(): ?\App\Models\User
    {
        return app(SpReportService::class)->supervisorFor($this->record);
    }

    private function fillFromRecord(): void
    {
        foreach (['nomor', 'coaching_id', 'kejadian', 'subject_clarification', 'analysis', 'kesimpulan', 'followup_plan'] as $field) {
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

        Notification::make()->title($success)->success()->send();
    }

    public function saveDraft(): void
    {
        $this->run(fn ($s) => $s->updateDraft($this->record, Auth::user(), $this->draftFields()), 'Draft disimpan');
    }

    /** Langkah FIX: isi dikunci. Tanda tangan baru muncul setelahnya, sebagai tombol terpisah. */
    public function sendForSigning(): void
    {
        $this->run(function ($s) {
            $s->updateDraft($this->record, Auth::user(), $this->draftFields());
            $s->sendForSigning($this->record->refresh(), Auth::user());
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

    /** Unggah BA yang sudah ditandatangani langsung -> BA selesai (lanjut ke keputusan). */
    public function uploadSigned(): void
    {
        $this->validate(['signedFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']], [], ['signedFile' => 'Berkas']);

        $this->run(fn ($s) => $s->uploadSigned($this->record, Auth::user(), $this->signedFile), 'Berita Acara bertanda tangan diunggah');
        $this->reset('signedFile');
    }

    public string $revision_reason = '';

    /** Revisi BA yang sudah FIX (TTD yang masuk dibatalkan, kembali ke draft). */
    public function revise(): void
    {
        $this->run(fn ($s) => $s->revise($this->record, Auth::user(), trim($this->revision_reason)), 'Berita Acara dibuka untuk revisi, tanda tangan sebelumnya dibatalkan');
        $this->revision_reason = '';
    }

    public function decide(string $decision): void
    {
        $this->run(fn ($s) => $s->decide($this->record, Auth::user(), SpReportDecision::from($decision)), 'Keputusan disimpan');
    }

    /** @return array<string, mixed> */
    private function draftFields(): array
    {
        return [
            'nomor' => $this->nomor,
            'coaching_id' => $this->coaching_id,
            'dasar_items' => $this->reasonPayload($this->dasar_items),
            'kejadian' => $this->kejadian,
            'subject_clarification' => $this->subject_clarification,
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
