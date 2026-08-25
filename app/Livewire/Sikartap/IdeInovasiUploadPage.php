<?php

namespace App\Livewire\Sikartap;

use App\Models\Sikartap\Sikartap;
use App\Models\Sikartap\SikartapIdeInovasi;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Rewrite upload_ide_inovasi.php legacy — self-service karyawan upload berkas ide inovasi
 * & lihat status 3 tingkat review (atasan wajib, direktur/komisaris opsional). Sengaja halaman
 * terpisah dari PengajuanKaryawanTetapPage (dokumen administratif) sama seperti legacy.
 */
#[Layout('components.layout', ['title' => 'Ide Inovasi'])]
class IdeInovasiUploadPage extends Component
{
    use WithFileUploads;

    public string $sikartapId;

    public $file = null;

    public function mount(): void
    {
        $sikartap = Sikartap::where('user_id', Auth::id())->latest()->first();

        abort_unless($sikartap, 404);
        abort_unless($sikartap->requiresIdeInovasi(), 403, 'Company Anda tidak mensyaratkan Ide Inovasi.');
        abort_unless($sikartap->status_wawancara === 'disarankan', 403, 'Ide Inovasi baru bisa diisi setelah wawancara disarankan.');

        $this->sikartapId = $sikartap->id;
    }

    public function getSikartapProperty(): Sikartap
    {
        return Sikartap::findOrFail($this->sikartapId);
    }

    public function getIdeInovasiProperty(): ?SikartapIdeInovasi
    {
        return SikartapIdeInovasi::where('sikartap_id', $this->sikartapId)->first();
    }

    public function getCanUploadProperty(): bool
    {
        $ideInovasi = $this->ideInovasi;

        return ! $ideInovasi || $ideInovasi->status_atasan === 'rejected';
    }

    public function upload(): void
    {
        abort_unless($this->canUpload, 403);

        $this->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,ppt,pptx'],
        ], [], ['file' => 'Berkas ide inovasi']);

        $path = $this->file->store('sikartap/ide-inovasi/' . Auth::id(), 'public');

        $existing = $this->ideInovasi;

        if ($existing) {
            if ($existing->file_ide_inovasi) {
                Storage::disk('public')->delete($existing->file_ide_inovasi);
            }

            // Reupload = reset total ke pending, sama persis upload_ide_inovasi.php legacy -
            // skor/catatan/status review sebelumnya (kalau ada, dari siklus tolak-upload ulang)
            // tidak relevan lagi buat berkas yang baru.
            $existing->update([
                'file_ide_inovasi' => $path,
                'catatan_penolakan' => null,
                'status_atasan' => 'pending',
                'catatan_atasan' => null,
                'reviewed_by_atasan' => null,
                'reviewed_at_atasan' => null,
                'skor_identifikasi_masalah' => null,
                'skor_analisis_masalah' => null,
                'skor_kualitas_inovasi' => null,
                'skor_potensi_manfaat' => null,
                'skor_presentasi' => null,
                'nilai_akhir_atasan' => null,
                'klasifikasi_atasan' => null,
                'status_direktur' => 'pending',
                'catatan_direktur' => null,
                'reviewed_by_direktur' => null,
                'reviewed_at_direktur' => null,
                'status_komisaris' => 'pending',
                'catatan_komisaris' => null,
                'reviewed_by_komisaris' => null,
                'reviewed_at_komisaris' => null,
            ]);
        } else {
            $this->sikartap->ideInovasi()->create(['file_ide_inovasi' => $path]);
        }

        $this->file = null;

        Notification::make()
            ->title('Berkas ide inovasi berhasil diupload')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.sikartap.ide-inovasi-upload-page');
    }
}
