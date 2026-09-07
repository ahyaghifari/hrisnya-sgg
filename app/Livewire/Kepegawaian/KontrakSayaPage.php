<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Models\ContractSubmission;
use App\Models\Setting;
use App\Services\ContractExtensionService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Self-service karyawan Kontrak: ajukan perpanjangan kontrak + unggah dokumen (daftar dinamis
 * per company). Sisi penilai di KontrakDetailPage. Pola OrientasiPage — lepas Approval Engine.
 */
#[Layout('components.layout', ['title' => 'Perpanjangan Kontrak Saya'])]
class KontrakSayaPage extends Component
{
    use WithFileUploads;

    public ?string $submissionId = null;

    public ?string $alasan = null;
    public ?int $usulanDurasi = null;

    public $docFile = null;
    public ?string $docLabel = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->isKontrakKaryawan(), 403);

        $contract = $this->contract;
        $this->submissionId = $contract
            ? ContractSubmission::where('user_contract_id', $contract->id)->latest()->value('id')
            : null;

        if ($this->current) {
            $this->alasan = $this->current->alasan;
            $this->usulanDurasi = $this->current->usulan_durasi_bulan;
        }
    }

    public function getContractProperty()
    {
        return Auth::user()->currentContract;
    }

    public function getCurrentProperty(): ?ContractSubmission
    {
        return $this->submissionId
            ? ContractSubmission::with('documents')->find($this->submissionId)
            : null;
    }

    public function getSubmissionsProperty()
    {
        return ContractSubmission::where('user_id', Auth::id())->latest()->get();
    }

    /** @return array<int, array{label:string, required:bool}> */
    public function getRequiredDocsProperty(): array
    {
        return app(ContractExtensionService::class)->requiredDocuments(Auth::user()->company_id);
    }

    public function getLeadDaysProperty(): int
    {
        return (int) Setting::resolve(
            KepegawaianSettingEnum::PENGAJUAN_KONTRAK_LEAD_HARI->value,
            Auth::user()->company_id,
            default: KepegawaianSettingEnum::PENGAJUAN_KONTRAK_LEAD_HARI->default(),
        );
    }

    public function getWindowOpensAtProperty(): ?Carbon
    {
        $end = $this->contract?->contract_end_date;

        if (! $end || $this->leadDays <= 0) {
            return null;
        }

        return Carbon::parse($end)->copy()->subDays($this->leadDays)->startOfDay();
    }

    public function getCanStartNewProperty(): bool
    {
        $contract = $this->contract;

        if (! $contract || ContractSubmission::where('user_contract_id', $contract->id)->exists()) {
            return false;
        }

        $opensAt = $this->windowOpensAt;

        return ! $opensAt || now()->gte($opensAt);
    }

    public function getCanReopenProperty(): bool
    {
        return $this->current && $this->current->status === 'dibatalkan';
    }

    public function startApplication(): void
    {
        abort_unless($this->canStartNew, 403);

        $contract = $this->contract;
        if (! $contract) {
            Notification::make()->title('Kontrak berjalan tidak ditemukan')->danger()->send();

            return;
        }

        $submission = ContractSubmission::create([
            'user_id' => Auth::id(),
            'user_contract_id' => $contract->id,
            'company_id' => Auth::user()->company_id,
            'status' => 'draft',
        ]);

        $this->submissionId = $submission->id;
    }

    public function reopenApplication(): void
    {
        abort_unless($this->canReopen, 403);

        $this->current->update([
            'status' => 'draft',
            'submitted_at' => null,
            'decision' => null,
            'decision_durasi_bulan' => null,
            'decision_note' => null,
            'decided_by' => null,
            'decided_at' => null,
            'new_contract_id' => null,
        ]);

        Notification::make()->title('Pengajuan dibuka lagi — lengkapi lalu kirim ulang')->success()->send();
    }

    public function uploadDoc(): void
    {
        $submission = $this->current;
        abort_unless($submission && $submission->status === 'draft', 403);

        $this->validate([
            'docLabel' => 'required|string',
            'docFile' => ['required', 'file', 'max:20480', 'mimes:pdf,ppt,pptx,jpg,jpeg,png'],
        ], [], [
            'docLabel' => 'jenis dokumen',
            'docFile' => 'file (PDF / PowerPoint / gambar)',
        ]);

        $path = $this->docFile->store('contract-extension/' . $submission->user_id, 'public');

        // 1 slot = 1 file: kalau sudah ada label sama, ganti.
        $existing = $submission->documents()->where('label', $this->docLabel)->first();
        if ($existing) {
            Storage::disk('public')->delete($existing->path);
            $existing->delete();
        }

        $submission->documents()->create([
            'label' => $this->docLabel,
            'path' => $path,
            'original_name' => $this->docFile->getClientOriginalName(),
        ]);

        $this->docFile = null;
        $this->docLabel = null;

        // Buang cache computed `current` — instance-nya sudah eager-load `documents` SEBELUM
        // baris baru dibuat, tanpa ini indikator ceklis dokumen tidak berubah saat re-render.
        unset($this->current);

        Notification::make()->title('Dokumen terunggah')->success()->send();
    }

    public function removeDoc(string $docId): void
    {
        $submission = $this->current;
        abort_unless($submission && $submission->status === 'draft', 403);

        $doc = $submission->documents()->whereKey($docId)->first();
        if ($doc) {
            Storage::disk('public')->delete($doc->path);
            $doc->delete();
        }

        unset($this->current);
    }

    public function submit(): void
    {
        $submission = $this->current;
        abort_unless($submission && $submission->status === 'draft', 403);

        try {
            $submission->update([
                'alasan' => $this->alasan,
                'usulan_durasi_bulan' => $this->usulanDurasi ?: null,
            ]);
            app(ContractExtensionService::class)->submit($submission, Auth::user());
            Notification::make()->title('Pengajuan perpanjangan kontrak terkirim')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal mengirim')->body($e->getMessage())->danger()->send();
        }
    }

    public function cancel(): void
    {
        $submission = $this->current;
        abort_unless($submission && $submission->user_id === Auth::id(), 403);

        try {
            app(ContractExtensionService::class)->cancel($submission, Auth::id());
            Notification::make()->title('Pengajuan dibatalkan')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal membatalkan')->body($e->getMessage())->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.kepegawaian.kontrak-saya-page');
    }
}
