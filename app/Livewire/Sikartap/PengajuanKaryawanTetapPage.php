<?php

namespace App\Livewire\Sikartap;

use App\Enum\Sikartap\SikartapDocumentType;
use App\Models\Sikartap\Sikartap;
use App\Models\Sikartap\SikartapDocumentAttachment;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Portal self-service karyawan/pelamar — start pengajuan + upload berkas administratif.
 * Rewrite dari register.php+berkas_diupload.php legacy, dengan fix IDOR (§ I.3 analisis):
 * file disimpan disk 'public' dengan nama file random bawaan Livewire/Laravel (bukan
 * user_id+field+time() yang predictable seperti legacy), ikut pola DocumentFormPage/KontrakPage.
 */
#[Layout('components.layout', ['title' => 'Pengajuan Karyawan Tetap'])]
class PengajuanKaryawanTetapPage extends Component
{
    use WithFileUploads;

    public ?string $sikartapId = null;

    /** @var array<string, mixed> keyed by SikartapDocumentType::value */
    public array $uploads = [];

    public function mount(): void
    {
        // Pengajuan TERAKHIR (bukan sekadar "yang ada") - karyawan bisa punya lebih dari 1
        // baris Sikartap sepanjang waktu kalau pernah reapply setelah ditolak, lihat
        // startApplication()/Sikartap::canReapplyAfterRejection().
        $this->sikartapId = Sikartap::where('user_id', Auth::id())->latest()->value('id');
    }

    public function getSikartapProperty(): ?Sikartap
    {
        if (! $this->sikartapId) {
            return null;
        }

        return Sikartap::with('documents')->find($this->sikartapId);
    }

    public function getDocumentTypesProperty(): array
    {
        return SikartapDocumentType::cases();
    }

    /** Boleh mulai pengajuan baru kalau belum pernah sama sekali, ATAU pengajuan terakhir
     * ditolak (bukan didiskualifikasi) - rewrite tombol "Buat Pengajuan Baru" history_pengajuan.php
     * legacy, lihat Sikartap::canReapplyAfterRejection(). */
    public function getCanStartNewProperty(): bool
    {
        return ! $this->sikartap || $this->sikartap->canReapplyAfterRejection();
    }

    public function startApplication(): void
    {
        abort_unless($this->canStartNew, 403);

        $sikartap = Sikartap::create(['user_id' => Auth::id()]);
        $this->sikartapId = $sikartap->id;
    }

    public function uploadDocument(string $docType): void
    {
        $sikartap = $this->sikartap;

        abort_unless($sikartap, 404);
        abort_if($sikartap->status_berkas === 'approved', 403, 'Berkas sudah disetujui, tidak bisa diubah lagi.');
        abort_if($sikartap->isGagal() || $sikartap->is_diskualifikasi, 403, 'Pengajuan ini sudah berakhir, tidak bisa diubah lagi.');

        $type = SikartapDocumentType::from($docType);

        $this->validate([
            "uploads.{$docType}" => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [], ["uploads.{$docType}" => $type->getLabel()]);

        $file = $this->uploads[$docType];

        $existing = $sikartap->documents()->where('doc_type', $docType)->first();

        if ($existing && $existing->file_path) {
            Storage::disk('public')->delete($existing->file_path);
        }

        $path = $file->store('sikartap/' . $sikartap->user_id, 'public');

        SikartapDocumentAttachment::updateOrCreate(
            ['sikartap_id' => $sikartap->id, 'doc_type' => $docType],
            ['file_path' => $path, 'status' => 'pending', 'catatan_penolakan' => null],
        );

        unset($this->uploads[$docType]);

        Notification::make()
            ->title($type->getLabel() . ' berhasil diupload')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.sikartap.pengajuan-karyawan-tetap-page');
    }
}
