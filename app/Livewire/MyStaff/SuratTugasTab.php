<?php

namespace App\Livewire\MyStaff;

use App\Models\SuratTugas\PenandatanganSuratTugas;
use App\Models\SuratTugas\SuratTugas;
use App\Services\OrgHierarchyService;
use App\Services\SuratTugas\SuratTugasService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Tab "Surat Perintah Tugas" di MyStaff - atasan menugaskan bawahan bekerja di luar kantor.
 * Surat disimpan sebagai draft dulu, baru diajukan untuk ditandatangani. Logika bisnis
 * (validasi bawahan, snapshot penanda tangan, efek presensi) ada di SuratTugasService; komponen
 * ini hanya form + daftar surat yang dibuat user ini.
 */
class SuratTugasTab extends Component
{
    public bool $showForm = false;

    /** Draft yang sedang diedit (null = surat baru). */
    public ?string $editingId = null;

    public ?string $nomor = null;

    public ?string $tugas = null;

    public ?string $tempat = null;

    public ?string $transportasi = null;

    public ?string $lain_lain = null;

    public ?string $waktu = null;

    /** @var array<int, string> */
    public array $peserta = [];

    /** @var array<int, array{start_date: ?string, end_date: ?string}> */
    public array $periode = [['start_date' => null, 'end_date' => null]];

    public function getBawahanProperty()
    {
        return app(OrgHierarchyService::class)->subordinates(Auth::user())->orderByName()->get();
    }

    /** Pemberi tugas company ini (diatur di Pengaturan Kepegawaian), null kalau belum diatur. */
    public function getPenandatanganProperty(): ?PenandatanganSuratTugas
    {
        return PenandatanganSuratTugas::where('company_id', Auth::user()->company_id)->first();
    }

    public function getDaftarProperty()
    {
        return SuratTugas::where('created_by', Auth::id())
            ->with(['peserta.user', 'periode', 'document'])
            ->latest()
            ->limit(20)
            ->get();
    }

    /** Surat yang riwayat audit-nya sedang dibuka (satu per waktu). */
    public ?string $auditFor = null;

    public function toggleAudit(string $id): void
    {
        $this->auditFor = $this->auditFor === $id ? null : $id;
    }

    public function buat(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        $surat = $this->ownDraft($id);

        $this->editingId = $surat->id;
        $this->nomor = $surat->nomor;
        $this->tugas = $surat->tugas;
        $this->tempat = $surat->tempat;
        $this->transportasi = $surat->transportasi;
        $this->lain_lain = $surat->lain_lain;
        $this->waktu = $surat->waktu;
        $this->peserta = $surat->peserta->pluck('user_id')->all();
        $this->periode = $surat->periode->map(fn ($p) => [
            'start_date' => $p->start_date->toDateString(),
            'end_date' => $p->end_date?->toDateString(),
        ])->all();
        $this->showForm = true;
    }

    public function batal(): void
    {
        $this->resetForm();
    }

    public function addPeriode(): void
    {
        $this->periode[] = ['start_date' => null, 'end_date' => null];
    }

    public function removePeriode(int $index): void
    {
        unset($this->periode[$index]);
        $this->periode = array_values($this->periode) ?: [['start_date' => null, 'end_date' => null]];
    }

    public function simpanDraft(): void
    {
        $this->persistDraft();
        $this->resetForm();

        Notification::make()->title('Draft Surat Perintah Tugas disimpan.')->success()->send();
    }

    /** Simpan lalu langsung ajukan untuk ditandatangani. */
    public function ajukan(): void
    {
        $draft = $this->persistDraft();
        app(SuratTugasService::class)->requestSignature($draft, Auth::user());
        $this->resetForm();

        Notification::make()->title('Surat Perintah Tugas diajukan, menunggu tanda tangan pemberi tugas.')->success()->send();
    }

    /** Ajukan draft dari daftar tanpa membuka form. */
    public function kirim(string $id): void
    {
        app(SuratTugasService::class)->requestSignature($this->ownDraft($id), Auth::user());

        Notification::make()->title('Surat Perintah Tugas diajukan, menunggu tanda tangan pemberi tugas.')->success()->send();
    }

    public function hapus(string $id): void
    {
        app(SuratTugasService::class)->deleteDraft($this->ownDraft($id), Auth::user());

        Notification::make()->title('Draft dihapus.')->success()->send();
    }

    private function persistDraft(): SuratTugas
    {
        $this->validate([
            'tugas' => 'required|string',
            'tempat' => 'required|string|max:255',
            'transportasi' => 'nullable|string|max:255',
            'lain_lain' => 'nullable|string',
            'waktu' => 'nullable|string|max:255',
            'nomor' => 'nullable|string|max:255',
            'peserta' => 'required|array|min:1',
            'periode' => 'required|array|min:1',
            'periode.*.start_date' => 'required|date',
            // Kosong = sampai selesai.
            'periode.*.end_date' => 'nullable|date|after_or_equal:periode.*.start_date',
        ], [
            'peserta.required' => 'Pilih minimal satu karyawan.',
            'periode.*.start_date.required' => 'Tanggal mulai wajib diisi.',
            'periode.*.end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        return app(SuratTugasService::class)->saveDraft([
            'nomor' => $this->nomor,
            'tugas' => $this->tugas,
            'tempat' => $this->tempat,
            'transportasi' => $this->transportasi,
            'lain_lain' => $this->lain_lain,
            'waktu' => $this->waktu,
            'peserta' => $this->peserta,
            'periode' => $this->periode,
        ], Auth::user(), $this->editingId ? $this->ownDraft($this->editingId) : null);
    }

    private function ownDraft(string $id): SuratTugas
    {
        $surat = SuratTugas::with(['peserta', 'periode'])->where('created_by', Auth::id())->findOrFail($id);
        abort_unless($surat->isDraft(), 403, 'Surat sudah diajukan, tidak bisa diubah.');

        return $surat;
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'nomor', 'tugas', 'tempat', 'transportasi', 'lain_lain', 'waktu', 'peserta']);
        $this->periode = [['start_date' => null, 'end_date' => null]];
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.my-staff.surat-tugas-tab');
    }
}
