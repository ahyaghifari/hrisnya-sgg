<?php

namespace App\Livewire\Sp;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpReportDecision;
use App\Models\Sp\SpReport;
use App\Services\Sp\SpReportService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail Berita Acara alur baru - sisi HC (instruksi user 2026-10-01). Isi halaman mengikuti
 * status (lihat SpReportService); semua aturan transisi & otorisasi ada di service, komponen ini
 * hanya meneruskan dan menampilkan pesan validasinya.
 */
#[Layout('components.layout', ['title' => 'Detail Berita Acara'])]
class BeritaAcaraDetailPage extends Component
{
    public SpReport $record;

    public ?string $nomor = null;
    public ?string $dasar_klarifikasi = null;
    public ?string $kejadian = null;

    public string $klarifikasi_karyawan = '';

    public ?string $klarifikasi_parafrase = null;
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
    }

    private function fillFromRecord(): void
    {
        foreach (['nomor', 'dasar_klarifikasi', 'kejadian', 'klarifikasi_parafrase', 'analysis', 'kesimpulan', 'followup_plan'] as $field) {
            $this->{$field} = $this->record->{$field};
        }
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
        $this->run(fn ($s) => $s->updateDraft($this->record, Auth::user(), [
            'nomor' => $this->nomor, 'dasar_klarifikasi' => $this->dasar_klarifikasi, 'kejadian' => $this->kejadian,
        ]), 'Draft disimpan');
    }

    public function requestClarification(): void
    {
        $this->saveDraft();
        $this->run(fn ($s) => $s->requestClarification($this->record, Auth::user()), 'Klarifikasi diminta dari karyawan');
    }

    public function fillClarification(): void
    {
        $this->run(fn ($s) => $s->fillClarificationByHc($this->record, Auth::user(), $this->klarifikasi_karyawan), 'Klarifikasi karyawan disimpan');
    }

    public function saveAnalysis(): void
    {
        $this->run(fn ($s) => $s->saveAnalysis($this->record, Auth::user(), $this->analysisFields()), 'Tersimpan');
    }

    public function sendForSigning(): void
    {
        $this->run(function ($s) {
            $s->saveAnalysis($this->record, Auth::user(), $this->analysisFields());
            $s->sendForSigning($this->record, Auth::user());
        }, 'Berita Acara dikirim untuk ditandatangani');
    }

    public function decide(string $decision): void
    {
        $this->run(fn ($s) => $s->decide($this->record, Auth::user(), SpReportDecision::from($decision)), 'Keputusan disimpan');
    }

    /** @return array<string, ?string> */
    private function analysisFields(): array
    {
        return [
            'klarifikasi_parafrase' => $this->klarifikasi_parafrase,
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
