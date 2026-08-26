<?php

namespace App\Livewire\Sikartap;

use App\Models\Sikartap\Sikartap;
use App\Models\Sikartap\SikartapIdeInovasi;
use App\Notifications\Sikartap\SikartapIdeInovasiDireview;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Form skoring atasan (tahap 1, wajib) — rubrik 5 kriteria bobot berbeda, direkonstruksi dari
 * upload_ide_inovasi.php legacy (lihat SikartapIdeInovasi::BOBOT/AMBANG_LULUS). Status
 * approved/rejected dihitung otomatis dari nilai akhir, atasan tidak pilih manual.
 */
#[Layout('components.layout', ['title' => 'Nilai Ide Inovasi'])]
class IdeInovasiAtasanReviewPage extends Component
{
    public string $sikartapId;

    public array $skor = [
        'skor_identifikasi_masalah' => 3,
        'skor_analisis_masalah' => 3,
        'skor_kualitas_inovasi' => 3,
        'skor_potensi_manfaat' => 3,
        'skor_presentasi' => 3,
    ];

    public string $catatan = '';

    public function mount(Sikartap $sikartap): void
    {
        $ideInovasi = $sikartap->ideInovasi;

        abort_unless($ideInovasi, 404);
        abort_unless($sikartap->canReviewIdeInovasiAsAtasan(Auth::user()), 403);
        abort_unless($ideInovasi->status_atasan === 'pending', 403, 'Ide inovasi ini sudah dinilai.');

        $this->sikartapId = $sikartap->id;
    }

    public function getSikartapProperty(): Sikartap
    {
        return Sikartap::with(['user', 'ideInovasi'])->findOrFail($this->sikartapId);
    }

    public function submit(): void
    {
        $this->validate([
            'skor.skor_identifikasi_masalah' => ['required', 'integer', 'min:0', 'max:5'],
            'skor.skor_analisis_masalah' => ['required', 'integer', 'min:0', 'max:5'],
            'skor.skor_kualitas_inovasi' => ['required', 'integer', 'min:0', 'max:5'],
            'skor.skor_potensi_manfaat' => ['required', 'integer', 'min:0', 'max:5'],
            'skor.skor_presentasi' => ['required', 'integer', 'min:0', 'max:5'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $sikartap = $this->sikartap;
        $ideInovasi = $sikartap->ideInovasi;

        abort_unless($ideInovasi && $ideInovasi->status_atasan === 'pending', 403);

        $nilaiAkhir = SikartapIdeInovasi::hitungNilaiAkhir($this->skor);
        $lulus = $nilaiAkhir >= SikartapIdeInovasi::AMBANG_LULUS;

        $ideInovasi->update([
            ...$this->skor,
            'nilai_akhir_atasan' => $nilaiAkhir,
            'klasifikasi_atasan' => SikartapIdeInovasi::klasifikasiDariNilai($nilaiAkhir),
            'status_atasan' => $lulus ? 'approved' : 'rejected',
            'catatan_atasan' => $this->catatan ?: null,
            'catatan_penolakan' => $lulus ? null : ($this->catatan ?: 'Nilai akhir di bawah ambang kelulusan.'),
            'reviewed_by_atasan' => Auth::id(),
            'reviewed_at_atasan' => now(),
        ]);

        $sikartap->user?->notify(new SikartapIdeInovasiDireview(
            $sikartap->id, 'atasan', $lulus ? 'approved' : 'rejected', $this->catatan ?: null
        ));

        Notification::make()
            ->title($lulus ? 'Ide inovasi disetujui' : 'Ide inovasi ditolak — nilai di bawah ambang')
            ->success()
            ->send();

        $this->redirectRoute('sikartap.ide-inovasi.atasan.queue', navigate: true);
    }

    public function render()
    {
        return view('livewire.sikartap.ide-inovasi-atasan-review-page');
    }
}
