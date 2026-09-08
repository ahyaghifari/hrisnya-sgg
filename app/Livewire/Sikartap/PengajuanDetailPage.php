<?php

namespace App\Livewire\Sikartap;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sikartap\SikartapDocumentType;
use App\Livewire\Sikartap\Concerns\ManagesSikartapTesWawancara;
use App\Models\Sikartap\Sikartap;
use App\Models\Sikartap\SikartapDocumentAttachment;
use App\Notifications\Sikartap\SikartapBerkasStatusChanged;
use App\Notifications\Sikartap\SikartapDocumentRejected;
use App\Notifications\Sikartap\SikartapHasilMcu;
use App\Notifications\Sikartap\SikartapIdeInovasiDireview;
use App\Services\Sikartap\SikartapDirectorDecisionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Portal detail pengajuan (sisi HRD/Direktur) — rewrite native Livewire dari
 * app/Filament/Resources/Sikartap/Pages/ViewSikartap.php + DocumentsRelationManager (yang
 * TETAP ada, dipakai kalau akses lewat /admin langsung). Business logic tidak diduplikasi:
 * keputusan direktur tetap lewat SikartapDirectorDecisionService, kolom lain di-update
 * langsung persis method Filament action yang sama.
 */
#[Layout('components.layout', ['title' => 'Detail Pengajuan'])]
class PengajuanDetailPage extends Component
{
    use ManagesSikartapTesWawancara;

    public Sikartap $sikartap;

    public ?string $activePanel = null;

    // Tolak dokumen individual
    public ?string $rejectingDocId = null;
    public string $documentRejectNote = '';

    // Tolak pengajuan (berkas)
    public string $applicationRejectNote = '';

    // Jadwal & hasil Tes/Wawancara: ManagesSikartapTesWawancara

    // Keputusan MCU
    public array $mcuDecisionForm = ['status_mcu' => null, 'catatan_mcu' => null];

    // Keputusan Direktur
    public string $directorNote = '';

    /** Tanggal TMT Karyawan Tetap — diisi HRD saat mengajukan ke Direktur. */
    public ?string $tmtTetap = null;

    // Review Ide Inovasi tahap opsional (Direktur/Komisaris) — 1 field catatan dipakai bergantian
    // sesuai panel aktif, sama pola $directorNote.
    public string $ideInovasiNote = '';

    public function mount(Sikartap $sikartap): void
    {
        $sikartap->load([
            'user.company', 'user.profile', 'documents', 'testSchedule', 'interviewSchedule',
            'mcuNurseAssessment', 'mcuLabResult', 'mcuRadiologyResult', 'mcuDoctorExamination',
            'ideInovasi', 'keputusanDirekturBy',
        ]);

        // Akses: HRD (View:Sikartap) ATAU Direktur yang ditunjuk (lepas Approval Engine —
        // Direktur tidak wajib punya permission HR, cukup pemegang jabatan Direktur).
        abort_unless(
            Auth::user()->can('View:Sikartap')
                || app(SikartapDirectorDecisionService::class)->canDecide($sikartap, Auth::user()),
            403,
        );

        $this->sikartap = $sikartap;
        $this->tmtTetap = $sikartap->tmt_tetap?->toDateString() ?? now()->toDateString();
    }

    protected function refreshRecord(): void
    {
        $this->sikartap->refresh();
        $this->sikartap->load([
            'documents', 'testSchedule', 'interviewSchedule',
            'mcuNurseAssessment', 'mcuLabResult', 'mcuRadiologyResult', 'mcuDoctorExamination',
            'ideInovasi', 'keputusanDirekturBy',
        ]);
    }

    public function getCanUpdateProperty(): bool
    {
        return Auth::user()->can('Update:Sikartap');
    }

    public function getCanApproveDirekturProperty(): bool
    {
        return $this->sikartap->status_keputusan_direktur === 'diajukan'
            && app(SikartapDirectorDecisionService::class)->canDecide($this->sikartap, Auth::user());
    }

    public function openPanel(string $panel): void
    {
        $this->activePanel = $panel;
        $this->resetErrorBag();

        match ($panel) {
            'testSchedule' => $this->testScheduleForm = $this->sikartap->testSchedule?->only(['tanggal_tes', 'waktu_tes', 'tempat_tes', 'keterangan'])
                ?? ['tanggal_tes' => null, 'waktu_tes' => null, 'tempat_tes' => null, 'keterangan' => null],
            'testResult' => $this->testResultForm = $this->sikartap->only(['nilai_tes', 'nilai_psikotes', 'status_tes', 'catatan_tes']),
            'interviewSchedule' => $this->interviewScheduleForm = $this->sikartap->interviewSchedule?->only(['tanggal_wawancara', 'waktu_wawancara', 'tempat_wawancara', 'pewawancara', 'pewawancara_2', 'keterangan'])
                ?? ['tanggal_wawancara' => null, 'waktu_wawancara' => null, 'tempat_wawancara' => null, 'pewawancara' => null, 'pewawancara_2' => null, 'keterangan' => null],
            'interviewResult' => $this->interviewResultForm = $this->sikartap->only(['status_wawancara', 'catatan_wawancara']),
            'mcuDecision' => $this->mcuDecisionForm = [
                'status_mcu' => $this->sikartap->status_mcu === 'pending' ? null : $this->sikartap->status_mcu,
                'catatan_mcu' => $this->sikartap->catatan_mcu ?? $this->sikartap->mcuDoctorExamination?->rekomendasi,
            ],
            default => null,
        };
    }

    public function closePanel(): void
    {
        $this->activePanel = null;
        $this->rejectingDocId = null;
        $this->documentRejectNote = '';
        $this->applicationRejectNote = '';
        $this->directorNote = '';
        $this->ideInovasiNote = '';
    }

    // ── Verifikasi berkas per-dokumen ──────────────────────────────────────

    public function approveDocument(string $documentId): void
    {
        abort_unless($this->canUpdate, 403);

        SikartapDocumentAttachment::where('id', $documentId)->update([
            'status' => 'approved',
            'catatan_penolakan' => null,
        ]);

        $this->refreshRecord();

        Notification::make()->title('Dokumen disetujui')->success()->send();
    }

    public function startRejectDocument(string $documentId): void
    {
        $this->rejectingDocId = $documentId;
        $this->documentRejectNote = '';
    }

    public function confirmRejectDocument(): void
    {
        abort_unless($this->canUpdate, 403);

        $this->validate(['documentRejectNote' => ['required', 'string']]);

        $document = SikartapDocumentAttachment::findOrFail($this->rejectingDocId);
        $document->update([
            'status' => 'rejected',
            'catatan_penolakan' => $this->documentRejectNote,
        ]);

        $this->sikartap->user?->notify(
            new SikartapDocumentRejected($this->sikartap->id, $document->doc_type->getLabel(), $this->documentRejectNote)
        );

        $this->rejectingDocId = null;
        $this->documentRejectNote = '';
        $this->refreshRecord();

        Notification::make()->title('Dokumen ditolak')->success()->send();
    }

    // ── Setujui semua / tolak pengajuan (berkas) ───────────────────────────

    public function approveAllBerkas(): void
    {
        abort_unless($this->canUpdate && $this->sikartap->status_berkas !== 'approved' && $this->sikartap->hasAllRequiredDocumentsUploaded(), 403);

        $this->sikartap->documents()->whereNotNull('file_path')->update([
            'status' => 'approved',
            'catatan_penolakan' => null,
        ]);

        $this->sikartap->update(['status_berkas' => 'approved', 'berkas_lengkap' => true]);

        $this->sikartap->user?->notify(new SikartapBerkasStatusChanged($this->sikartap->id, 'approved'));

        $this->refreshRecord();

        Notification::make()->title('Berkas disetujui, lanjut ke proses tes')->success()->send();
    }

    public function confirmRejectApplication(): void
    {
        abort_unless($this->canUpdate && $this->sikartap->status_berkas !== 'approved', 403);

        $this->validate(['applicationRejectNote' => ['required', 'string']]);

        $this->sikartap->documents()->whereNotNull('file_path')->update([
            'status' => 'rejected',
            'catatan_penolakan' => $this->applicationRejectNote,
        ]);

        $this->sikartap->update(['status_berkas' => 'rejected']);

        $this->sikartap->user?->notify(new SikartapBerkasStatusChanged($this->sikartap->id, 'rejected'));

        $this->closePanel();
        $this->refreshRecord();

        Notification::make()->title('Pengajuan ditolak')->success()->send();
    }

    // ── Tes & Wawancara: jadwal + hasil di ManagesSikartapTesWawancara ──

    /** Record tunggal yang disunting panel jadwal/hasil di halaman detail ini. */
    protected function sikartapContext(): Sikartap
    {
        return $this->sikartap;
    }

    protected function afterSikartapSave(): void
    {
        $this->refreshRecord();
    }

    // ── MCU ─────────────────────────────────────────────────────────────

    public function saveMcuDecision(): void
    {
        abort_unless($this->canUpdate && $this->sikartap->status_wawancara === 'disarankan' && $this->sikartap->mcuDoctorExamination !== null, 403);

        $this->validate([
            'mcuDecisionForm.status_mcu' => ['required', 'in:fit,unfit'],
        ]);

        $this->sikartap->update($this->mcuDecisionForm);

        $this->sikartap->user?->notify(new SikartapHasilMcu($this->sikartap->id, $this->mcuDecisionForm['status_mcu']));

        $this->closePanel();
        $this->refreshRecord();

        Notification::make()->title('Hasil MCU disimpan')->success()->send();
    }

    // ── Keputusan Direktur ──────────────────────────────────────────────

    public function ajukanKeputusanDirektur(): void
    {
        abort_unless(
            $this->canUpdate
                && $this->sikartap->isEligibleForDirectorDecision()
                && in_array($this->sikartap->status_keputusan_direktur, ['pending', 'diajukan'], true),
            403,
        );

        $this->validate(
            ['tmtTetap' => ['required', 'date']],
            [],
            ['tmtTetap' => 'tanggal TMT Karyawan Tetap'],
        );

        try {
            app(SikartapDirectorDecisionService::class)->submitForDecision($this->sikartap, $this->tmtTetap);

            Notification::make()->title('Pengajuan dikirim ke Direktur')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal mengajukan')->body($e->getMessage())->danger()->send();
        }

        $this->refreshRecord();
    }

    public function confirmApproveDirektur(): void
    {
        abort_unless($this->canApproveDirektur, 403);

        try {
            app(SikartapDirectorDecisionService::class)->approve($this->sikartap, Auth::user(), $this->directorNote);
            Notification::make()->title('Keputusan tersimpan')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal menyimpan')->body($e->getMessage())->danger()->send();
        }

        $this->closePanel();
        $this->refreshRecord();
    }

    public function confirmRejectDirektur(): void
    {
        abort_unless($this->canApproveDirektur, 403);

        $this->validate(['directorNote' => ['required', 'string']]);

        try {
            app(SikartapDirectorDecisionService::class)->reject($this->sikartap, Auth::user(), $this->directorNote);
            Notification::make()->title('Keputusan tersimpan')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal menyimpan')->body($e->getMessage())->danger()->send();
        }

        $this->closePanel();
        $this->refreshRecord();
    }

    // ── Ide Inovasi — review opsional Direktur/Komisaris (tidak blocking, independen satu
    // sama lain, beda dari tahap atasan yang wajib & sudah dinilai lewat halaman terpisah,
    // lihat IdeInovasiAtasanReviewPage). ────────────────────────────────────

    public function getCanReviewIdeInovasiDirekturProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::NILAI_IDE_INOVASI_DIREKTUR->value)
            && $this->sikartap->ideInovasi
            && $this->sikartap->ideInovasi->status_direktur === 'pending';
    }

    public function getCanReviewIdeInovasiKomisarisProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::NILAI_IDE_INOVASI_KOMISARIS->value)
            && $this->sikartap->ideInovasi
            && $this->sikartap->ideInovasi->status_komisaris === 'pending';
    }

    private function reviewIdeInovasiTier(string $tier, string $status): void
    {
        $this->sikartap->ideInovasi->update([
            "status_{$tier}" => $status,
            "catatan_{$tier}" => $this->ideInovasiNote ?: null,
            "reviewed_by_{$tier}" => Auth::id(),
            "reviewed_at_{$tier}" => now(),
        ]);

        $this->sikartap->user?->notify(new SikartapIdeInovasiDireview($this->sikartap->id, $tier, $status, $this->ideInovasiNote ?: null));

        $this->closePanel();
        $this->refreshRecord();

        Notification::make()->title('Penilaian ide inovasi tersimpan')->success()->send();
    }

    public function approveIdeInovasiDirektur(): void
    {
        abort_unless($this->canReviewIdeInovasiDirektur, 403);
        $this->reviewIdeInovasiTier('direktur', 'approved');
    }

    public function rejectIdeInovasiDirektur(): void
    {
        abort_unless($this->canReviewIdeInovasiDirektur, 403);
        $this->validate(['ideInovasiNote' => ['required', 'string']]);
        $this->reviewIdeInovasiTier('direktur', 'rejected');
    }

    public function approveIdeInovasiKomisaris(): void
    {
        abort_unless($this->canReviewIdeInovasiKomisaris, 403);
        $this->reviewIdeInovasiTier('komisaris', 'approved');
    }

    public function rejectIdeInovasiKomisaris(): void
    {
        abort_unless($this->canReviewIdeInovasiKomisaris, 403);
        $this->validate(['ideInovasiNote' => ['required', 'string']]);
        $this->reviewIdeInovasiTier('komisaris', 'rejected');
    }

    /** Timeline ringkas tahap Keputusan Direktur (lepas Approval Engine 2026-09-07 — cuma 1
     * langkah: diajukan HRD -> keputusan Direktur). Array kosong kalau belum diajukan. */
    public function getTimelineProperty(): array
    {
        $s = $this->sikartap;

        if (! in_array($s->status_keputusan_direktur, ['diajukan', 'approved', 'rejected'], true)) {
            return [];
        }

        $decided = in_array($s->status_keputusan_direktur, ['approved', 'rejected'], true);

        return [
            [
                'label' => 'Diajukan ke Direktur',
                'actor' => null,
                'date' => $s->keputusan_direktur_diajukan_at,
                'state' => 'done',
            ],
            [
                'label' => 'Keputusan Direktur',
                'actor' => $s->keputusanDirekturBy?->name,
                'date' => $s->keputusan_direktur_at,
                'state' => $decided ? $s->status_keputusan_direktur : 'current',
            ],
        ];
    }

    public function getDocumentTypesProperty(): array
    {
        return SikartapDocumentType::cases();
    }

    public function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function render()
    {
        return view('livewire.sikartap.pengajuan-detail-page');
    }
}
