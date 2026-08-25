<?php

namespace App\Livewire\Sikartap;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sikartap\SikartapDocumentType;
use App\Models\Sikartap\Sikartap;
use App\Models\Sikartap\SikartapDocumentAttachment;
use App\Services\Approval\ApprovalPermissionService;
use App\Services\Approval\ApprovalTimelineService;
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
    public Sikartap $sikartap;

    public ?string $activePanel = null;

    // Tolak dokumen individual
    public ?string $rejectingDocId = null;
    public string $documentRejectNote = '';

    // Tolak pengajuan (berkas)
    public string $applicationRejectNote = '';

    // Jadwal & hasil tes
    public array $testScheduleForm = ['tanggal_tes' => null, 'waktu_tes' => null, 'tempat_tes' => null, 'keterangan' => null];
    public array $testResultForm = ['nilai_tes' => null, 'nilai_psikotes' => null, 'status_tes' => null, 'catatan_tes' => null];

    // Jadwal & hasil wawancara
    public array $interviewScheduleForm = ['tanggal_wawancara' => null, 'waktu_wawancara' => null, 'tempat_wawancara' => null, 'pewawancara' => null, 'pewawancara_2' => null, 'keterangan' => null];
    public array $interviewResultForm = ['status_wawancara' => null, 'catatan_wawancara' => null];

    // Keputusan MCU
    public array $mcuDecisionForm = ['status_mcu' => null, 'catatan_mcu' => null];

    // Keputusan Direktur
    public string $directorNote = '';

    // Review Ide Inovasi tahap opsional (Direktur/Komisaris) — 1 field catatan dipakai bergantian
    // sesuai panel aktif, sama pola $directorNote.
    public string $ideInovasiNote = '';

    public function mount(Sikartap $sikartap): void
    {
        abort_unless(Auth::user()->can('View:Sikartap'), 403);

        $sikartap->load([
            'user.company', 'documents', 'testSchedule', 'interviewSchedule',
            'mcuNurseAssessment', 'mcuLabResult', 'mcuRadiologyResult', 'mcuDoctorExamination',
            'ideInovasi', 'approvalSnapshot',
        ]);

        $this->sikartap = $sikartap;
    }

    protected function refreshRecord(): void
    {
        $this->sikartap->refresh();
        $this->sikartap->load([
            'documents', 'testSchedule', 'interviewSchedule',
            'mcuNurseAssessment', 'mcuLabResult', 'mcuRadiologyResult', 'mcuDoctorExamination',
            'ideInovasi', 'approvalSnapshot',
        ]);
    }

    public function getCanUpdateProperty(): bool
    {
        return Auth::user()->can('Update:Sikartap');
    }

    public function getCanApproveDirekturProperty(): bool
    {
        if (! $this->sikartap->approvalSnapshot) {
            return false;
        }

        return app(ApprovalPermissionService::class)->canApprove($this->sikartap->approvalSnapshot->id, Auth::user());
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

        SikartapDocumentAttachment::where('id', $this->rejectingDocId)->update([
            'status' => 'rejected',
            'catatan_penolakan' => $this->documentRejectNote,
        ]);

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

        $this->closePanel();
        $this->refreshRecord();

        Notification::make()->title('Pengajuan ditolak')->success()->send();
    }

    // ── Tes ─────────────────────────────────────────────────────────────

    public function saveTestSchedule(): void
    {
        abort_unless($this->canUpdate && $this->sikartap->status_berkas === 'approved', 403);

        $this->validate([
            'testScheduleForm.tanggal_tes' => ['required', 'date'],
            'testScheduleForm.waktu_tes' => ['required'],
            'testScheduleForm.tempat_tes' => ['required', 'string'],
        ]);

        $this->sikartap->testSchedule()->updateOrCreate(['sikartap_id' => $this->sikartap->id], $this->testScheduleForm);

        $this->closePanel();
        $this->refreshRecord();

        Notification::make()->title('Jadwal tes disimpan')->success()->send();
    }

    public function saveTestResult(): void
    {
        abort_unless($this->canUpdate && $this->sikartap->testSchedule !== null, 403);

        $this->validate([
            'testResultForm.nilai_tes' => ['required', 'numeric'],
            'testResultForm.nilai_psikotes' => ['required', 'numeric'],
            'testResultForm.status_tes' => ['required', 'in:lulus,tidak_lulus'],
        ]);

        $this->sikartap->update($this->testResultForm);

        $this->closePanel();
        $this->refreshRecord();

        Notification::make()->title('Hasil tes disimpan')->success()->send();
    }

    // ── Wawancara ───────────────────────────────────────────────────────

    public function saveInterviewSchedule(): void
    {
        abort_unless($this->canUpdate && $this->sikartap->status_tes === 'lulus', 403);

        $this->validate([
            'interviewScheduleForm.tanggal_wawancara' => ['required', 'date'],
            'interviewScheduleForm.waktu_wawancara' => ['required'],
            'interviewScheduleForm.tempat_wawancara' => ['required', 'string'],
            'interviewScheduleForm.pewawancara' => ['required', 'string'],
        ]);

        $this->sikartap->interviewSchedule()->updateOrCreate(['sikartap_id' => $this->sikartap->id], $this->interviewScheduleForm);

        $this->closePanel();
        $this->refreshRecord();

        Notification::make()->title('Jadwal wawancara disimpan')->success()->send();
    }

    public function saveInterviewResult(): void
    {
        abort_unless($this->canUpdate && $this->sikartap->interviewSchedule !== null, 403);

        $this->validate([
            'interviewResultForm.status_wawancara' => ['required', 'in:disarankan,tidak_disarankan'],
        ]);

        $this->sikartap->update($this->interviewResultForm);

        $this->closePanel();
        $this->refreshRecord();

        Notification::make()->title('Hasil wawancara disimpan')->success()->send();
    }

    // ── MCU ─────────────────────────────────────────────────────────────

    public function saveMcuDecision(): void
    {
        abort_unless($this->canUpdate && $this->sikartap->status_wawancara === 'disarankan' && $this->sikartap->mcuDoctorExamination !== null, 403);

        $this->validate([
            'mcuDecisionForm.status_mcu' => ['required', 'in:fit,unfit'],
        ]);

        $this->sikartap->update($this->mcuDecisionForm);

        $this->closePanel();
        $this->refreshRecord();

        Notification::make()->title('Hasil MCU disimpan')->success()->send();
    }

    // ── Keputusan Direktur ──────────────────────────────────────────────

    public function ajukanKeputusanDirektur(): void
    {
        abort_unless($this->canUpdate && $this->sikartap->isEligibleForDirectorDecision() && $this->sikartap->approvalSnapshot === null, 403);

        try {
            app(SikartapDirectorDecisionService::class)->submitForDecision($this->sikartap);

            Notification::make()->title('Pengajuan dikirim ke Direktur')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Gagal mengajukan')->body($e->getMessage())->danger()->send();
        }

        $this->refreshRecord();
    }

    public function confirmApproveDirektur(): void
    {
        abort_unless($this->canApproveDirektur, 403);

        app(SikartapDirectorDecisionService::class)->approve($this->sikartap, $this->directorNote);

        $this->closePanel();
        $this->refreshRecord();

        Notification::make()->title('Keputusan tersimpan')->success()->send();
    }

    public function confirmRejectDirektur(): void
    {
        abort_unless($this->canApproveDirektur, 403);

        $this->validate(['directorNote' => ['required', 'string']]);

        app(SikartapDirectorDecisionService::class)->reject($this->sikartap, $this->directorNote);

        $this->closePanel();
        $this->refreshRecord();

        Notification::make()->title('Keputusan tersimpan')->success()->send();
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

    /** Timeline visual "Diajukan -> tiap step -> selesai" khusus tahap Keputusan Direktur
     * (satu-satunya tahap yang beneran lewat Approval Engine, lihat § Approval Engine vs custom
     * di rencana-implementasi-sikartap.md) - array kosong kalau belum diajukan ke Direktur. */
    public function getTimelineProperty(): array
    {
        return app(ApprovalTimelineService::class)->build($this->sikartap);
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
