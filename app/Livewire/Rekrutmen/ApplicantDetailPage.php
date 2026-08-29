<?php

namespace App\Livewire\Rekrutmen;

use App\Services\Job\RekrutmenSyncService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Detail 1 pelamar — data live dari project Rekrutmen eksternal.
 * `jobApplicationId` = ULID milik project eksternal, bukan model HRIS.
 *
 * - getApplication(): info dasar + timeline status (selalu ada)
 * - getCandidate(): data diri lengkap (candidate + 8 tabel anak) — cuma setelah onboarding/passed
 *
 * Fase 3: aksi Seleksi Berkas (applied→screening, screening→shortlisted/rejected). Perubahan
 * dikirim ke Rekrutmen lewat RekrutmenSyncService::updateApplicationStatus (Rekrutmen yang
 * validasi transisi + jalankan efek samping). ponytail: tanpa mekanisme draft/publish seperti
 * ScreeningDecision di project eksternal — status change via API sudah "publish"-nya. Alasan
 * penolakan belum dicatat (tabel status log eksternal belum punya kolom note) — follow-up.
 */
#[Layout('components.layout', ['title' => 'Detail Pelamar'])]
class ApplicantDetailPage extends Component
{
    use WithFileUploads;

    public string $jobApplicationId;

    public bool $showTolakForm = false;

    // Form jadwal wawancara
    public ?string $intvDate = null;
    public ?string $intvTime = null;
    public ?string $intvLocation = null;
    public ?string $intvNotes = null;

    // Offering letter
    public $offeringFile = null;

    // Keputusan MCU
    public ?string $mcuCatatan = null;

    /** Urutan tahap buat stepper timeline. */
    public const STAGES = ['applied', 'screening', 'shortlisted', 'interview', 'offering', 'mcu', 'onboarding', 'passed'];

    public function mount(string $jobApplication): void
    {
        abort_unless(Auth::user()->can('View:JobOpenings'), 403);

        $this->jobApplicationId = $jobApplication;
    }

    public function getApplicationProperty(): ?array
    {
        return app(RekrutmenSyncService::class)->getApplication($this->jobApplicationId);
    }

    public function getCandidateProperty(): ?array
    {
        return app(RekrutmenSyncService::class)->getCandidate($this->jobApplicationId);
    }

    public function getCanDecideProperty(): bool
    {
        return Auth::user()->can('Update:JobOpenings');
    }

    /** State MCU — cuma di-fetch kalau status `mcu` (hemat 1 call API tiap render). */
    public function getMcuStateProperty(): ?array
    {
        if (($this->application['status'] ?? null) !== 'mcu') {
            return null;
        }

        return app(RekrutmenSyncService::class)->getMcu($this->jobApplicationId);
    }

    public function mcuFormUrl(): string
    {
        return route('rekrutmen.pelamar.mcu', $this->jobApplicationId);
    }

    public function putuskanMcu(string $result): void
    {
        abort_unless($this->canDecide, 403);
        abort_unless(in_array($result, ['fit', 'unfit'], true), 422);

        $this->notify(app(RekrutmenSyncService::class)->decideMcu($this->jobApplicationId, $result, $this->mcuCatatan ?: null));
    }

    public function getCanCreateEmployeeProperty(): bool
    {
        return Auth::user()->can('create', \App\Models\User::class);
    }

    public function selesaikanOnboarding(): void
    {
        abort_unless($this->canDecide, 403);

        $this->notify(app(RekrutmenSyncService::class)->completeOnboarding($this->jobApplicationId));
    }

    public function buatAkunKaryawan(): void
    {
        abort_unless($this->canCreateEmployee, 403);

        $result = app(\App\Services\Rekrutmen\CandidateOnboardingService::class)
            ->createEmployeeFromCandidate($this->jobApplicationId);

        Notification::make()
            ->title($result['ok'] ? 'Akun karyawan siap' : 'Gagal membuat akun')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    public function documentUrl(string $documentId): ?string
    {
        return route('rekrutmen.pelamar.dokumen', ['jobApplication' => $this->jobApplicationId, 'document' => $documentId]);
    }

    public function prosesSeleksi(): void
    {
        $this->transition('screening');
    }

    public function loloskanBerkas(): void
    {
        $this->transition('shortlisted');
    }

    public function tolak(): void
    {
        $this->transition('rejected');
        $this->showTolakForm = false;
    }

    public function jadwalkanWawancara(): void
    {
        abort_unless($this->canDecide, 403);

        $this->validate([
            'intvDate' => 'required|date',
            'intvTime' => 'nullable|date_format:H:i',
            'intvLocation' => 'nullable|string|max:255',
            'intvNotes' => 'nullable|string',
        ]);

        $this->notify(app(RekrutmenSyncService::class)->scheduleInterview($this->jobApplicationId, [
            'interview_date' => $this->intvDate,
            'interview_time' => $this->intvTime ?: null,
            'location' => $this->intvLocation ?: null,
            'notes' => $this->intvNotes ?: null,
        ]));
    }

    public function catatHasilWawancara(string $result): void
    {
        abort_unless($this->canDecide, 403);
        abort_unless(in_array($result, ['lulus', 'tidak_lulus'], true), 422);

        $this->notify(app(RekrutmenSyncService::class)->submitInterviewResult($this->jobApplicationId, $result));
    }

    public function unggahOfferingLetter(): void
    {
        abort_unless($this->canDecide, 403);

        $this->validate(['offeringFile' => 'required|file|mimes:pdf,doc,docx|max:5120']);

        $this->notify(app(RekrutmenSyncService::class)->uploadOfferingLetter($this->jobApplicationId, $this->offeringFile));
        $this->reset('offeringFile');
    }

    public function lanjutSetelahOffering(): void
    {
        abort_unless($this->canDecide, 403);

        $this->notify(app(RekrutmenSyncService::class)->advanceFromOffering($this->jobApplicationId));
    }

    public function offeringLetterUrl(string $type): string
    {
        return route('rekrutmen.pelamar.offering-letter', ['jobApplication' => $this->jobApplicationId, 'type' => $type]);
    }

    private function transition(string $status): void
    {
        abort_unless($this->canDecide, 403);

        $this->notify(app(RekrutmenSyncService::class)->updateApplicationStatus($this->jobApplicationId, $status));
    }

    /** @param array{ok:bool,message:string} $result */
    private function notify(array $result): void
    {
        Notification::make()
            ->title($result['ok'] ? 'Berhasil' : 'Gagal')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    public function render()
    {
        return view('livewire.rekrutmen.applicant-detail-page');
    }
}
