<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Models\OrientationSubmission;
use App\Models\Setting;
use App\Services\OrientationService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Self-service karyawan Orientasi: ajukan penilaian orientasi + unggah bukti kegiatan, lalu
 * kirim ke Approval Engine. Pola Sikartap (PengajuanKaryawanTetapPage). Sisi penilai ada di
 * OrientasiDetailPage.
 */
#[Layout('components.layout', ['title' => 'Orientasi Saya'])]
class OrientasiPage extends Component
{
    use WithFileUploads;

    public ?string $submissionId = null;

    public $buktiFile = null;

    /** Usulan jadwal presentasi kegiatan (diisi karyawan saat draft). */
    public ?string $presentasiDate = null;
    public ?string $presentasiTime = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->isOrientasiKaryawan(), 403);

        // Halaman ini cuma menampilkan orientasi yang AKTIF (periode terbaru). Pengajuan +
        // skor periode lama disembunyikan — muncul ringkas di daftar "Riwayat Pengajuan" &
        // periode lama di-collapse.
        $period = $this->period;
        $this->submissionId = $period
            ? OrientationSubmission::where('user_orientation_id', $period->id)->latest()->value('id')
            : null;

        if ($this->current?->presentation_proposed_at) {
            $this->presentasiDate = $this->current->presentation_proposed_at->toDateString();
            $this->presentasiTime = $this->current->presentation_proposed_at->format('H:i');
        }
    }

    public function getCurrentProperty(): ?OrientationSubmission
    {
        return $this->submissionId
            ? OrientationSubmission::with('assessments.scores')->find($this->submissionId)
            : null;
    }

    public function getSubmissionsProperty()
    {
        return OrientationSubmission::where('user_id', Auth::id())->latest()->get();
    }

    /** Periode orientasi karyawan SAAT INI (baris user_orientations terbaru). Pengajuan
     *  penilaian selalu menempel ke periode ini; kalau null berarti periode belum tercatat. */
    public function getPeriodProperty(): ?\App\Models\User\UserOrientation
    {
        return Auth::user()->orientations()->orderByDesc('start_date')->first();
    }

    /** Periode-periode lama (sebelum yang aktif) — ditampilkan collapse sebagai riwayat. */
    public function getPastPeriodsProperty()
    {
        return Auth::user()->orientations()->orderByDesc('start_date')->get()->slice(1)->values();
    }

    /** Ambang hari sebelum periode berakhir, pengajuan orientasi baru boleh dibuat. 0 = bebas. */
    public function getLeadDaysProperty(): int
    {
        return (int) Setting::resolve(
            KepegawaianSettingEnum::PENGAJUAN_ORIENTASI_LEAD_HARI->value,
            Auth::user()->company_id,
            default: KepegawaianSettingEnum::PENGAJUAN_ORIENTASI_LEAD_HARI->default(),
        );
    }

    /** Tanggal paling awal pengajuan boleh dibuat untuk periode aktif (null = bebas / tak ada periode). */
    public function getWindowOpensAtProperty(): ?Carbon
    {
        $period = $this->period;

        if (! $period?->end_date || $this->leadDays <= 0) {
            return null;
        }

        return $period->end_date->copy()->subDays($this->leadDays)->startOfDay();
    }

    /**
     * Pengajuan BARU dibuat cuma kalau periode orientasi TERBARU belum punya pengajuan sama
     * sekali. Jadi:
     *  - belum pernah ngajukan → boleh.
     *  - sudah dinilai/selesai → periode ini sudah ada pengajuannya → TIDAK boleh (keputusan
     *    lanjut_kontrak = bukan orientasi lagi/mount 403; tidak_dilanjutkan = tidak ada
     *    periode baru).
     *  - dibatalkan → buka baris yang sama (getCanReopenProperty), bukan baris baru.
     */
    public function getCanStartNewProperty(): bool
    {
        $period = $this->period;

        if (! $period || OrientationSubmission::where('user_orientation_id', $period->id)->exists()) {
            return false;
        }

        // Jendela pengajuan: mulai N hari sebelum periode berakhir (per perusahaan).
        $opensAt = $this->windowOpensAt;

        return ! $opensAt || now()->gte($opensAt);
    }

    /** Pengajuan yang dibatalkan bisa diajukan ULANG di baris yang sama (keputusan cuma
     *  Direkomendasikan untuk Kontrak Kerja / Tidak Dilanjutkan). */
    public function getCanReopenProperty(): bool
    {
        return $this->current && $this->current->status === 'dibatalkan';
    }

    public function startApplication(): void
    {
        abort_unless($this->canStartNew, 403);

        $period = $this->period;

        if (! $period) {
            Notification::make()
                ->title('Periode orientasi belum tercatat')
                ->body('Pengajuan penilaian butuh data periode orientasi. Hubungi HR/HC.')
                ->danger()
                ->send();

            return;
        }

        $submission = OrientationSubmission::create([
            'user_id' => Auth::id(),
            'user_orientation_id' => $period->id,
            'company_id' => Auth::user()->company_id,
            'status' => 'draft',
        ]);

        $this->submissionId = $submission->id;
    }

    /** Balikin pengajuan yang ditolak/dibatalkan ke draft — karyawan perbaiki bukti lalu kirim
     *  lagi. Baris pengajuan TIDAK berubah (id sama), skor/keputusan lama dibersihkan. */
    public function reopenApplication(): void
    {
        abort_unless($this->canReopen, 403);

        $submission = $this->current;
        $submission->assessments()->delete();
        $submission->update([
            'status' => 'draft',
            'submitted_at' => null,
            'decision' => null,
            'decision_note' => null,
            'decided_by' => null,
            'decided_at' => null,
            'extended_until' => null,
            'effective_end_date' => null,
            'presentation_confirmed_at' => null,
            'presentation_note' => null,
            'presentation_decided_by' => null,
            'presentation_decided_at' => null,
            'presentation_hr_at' => null,
            'presentation_hr_by' => null,
            'presentation_support_at' => null,
            'presentation_support_by' => null,
        ]);

        Notification::make()->title('Pengajuan dibuka lagi — perbaiki bukti lalu kirim ulang')->success()->send();
    }

    /** Simpan usulan jadwal presentasi ke draft (biar persist saat reload, sebelum Kirim). */
    public function savePresentasi(): void
    {
        $submission = $this->current;
        abort_unless($submission && $submission->status === 'draft', 403);

        $this->validate(
            ['presentasiDate' => ['required', 'date'], 'presentasiTime' => ['required']],
            [],
            ['presentasiDate' => 'tanggal presentasi', 'presentasiTime' => 'jam presentasi'],
        );

        $submission->update([
            'presentation_proposed_at' => Carbon::parse("{$this->presentasiDate} {$this->presentasiTime}"),
        ]);

        unset($this->current);

        Notification::make()->title('Usulan jadwal presentasi tersimpan')->success()->send();
    }

    public function uploadBukti(): void
    {
        $submission = $this->current;
        abort_unless($submission && $submission->status === 'draft', 403);

        $this->validate(
            ['buktiFile' => ['required', 'file', 'max:20480', 'mimes:pdf,ppt,pptx']],
            [],
            ['buktiFile' => 'bukti kegiatan (PDF atau PowerPoint)'],
        );

        $path = $this->buktiFile->store('orientation/' . $submission->user_id, 'public');

        $attachments = $submission->attachments ?? [];
        $attachments[] = ['path' => $path, 'name' => $this->buktiFile->getClientOriginalName()];
        $submission->update(['attachments' => $attachments]);

        $this->buktiFile = null;

        Notification::make()->title('Bukti kegiatan terunggah')->success()->send();
    }

    public function removeBukti(int $index): void
    {
        $submission = $this->current;
        abort_unless($submission && $submission->status === 'draft', 403);

        $attachments = $submission->attachments ?? [];

        if (isset($attachments[$index])) {
            Storage::disk('public')->delete($attachments[$index]['path']);
            unset($attachments[$index]);
            $submission->update(['attachments' => array_values($attachments)]);
        }
    }

    public function submit(): void
    {
        $submission = $this->current;
        abort_unless($submission && $submission->status === 'draft', 403);

        try {
            app(OrientationService::class)->submit($submission, Auth::user());

            Notification::make()->title('Pengajuan orientasi terkirim')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal mengirim')->body($e->getMessage())->danger()->send();
        }
    }

    public function cancel(): void
    {
        $submission = $this->current;
        abort_unless($submission && $submission->user_id === Auth::id(), 403);

        try {
            app(OrientationService::class)->cancel($submission, Auth::id());
            Notification::make()->title('Pengajuan dibatalkan')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal membatalkan')->body($e->getMessage())->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.kepegawaian.orientasi-page');
    }
}
