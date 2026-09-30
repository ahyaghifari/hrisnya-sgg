<?php

namespace App\Livewire\Rekrutmen\Mcu;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Rekrutmen\Mcu\Concerns\HandlesMcuFileUpload;
use App\Models\Rekrutmen\Company;
use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\McuInvitee;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Antrian MCU Rekrutmen — pengganti App\Filament\Rekrutmen\Pages\McuStationPage (dulu halaman
 * panel Filament), dipindah ke portal supaya staf klinis tidak perlu akses panel /rekrutmen.
 * Cukup pilih perusahaan — antrian menampilkan semua pelamar berstatus `mcu` yang sudah
 * diundang, lintas lowongan. Isi per stasiun sesuai permission INPUT_MCU_*.
 *
 * Perawat, lab & radiologi diisi lewat MODAL di halaman ini (keputusan user 2026-09-30): isiannya
 * cuma unggah satu berkas, jadi tidak perlu pindah halaman. Dokter tetap punya halaman sendiri
 * karena dia perlu membaca dulu berkas ketiga stasiun itu sebelum memutuskan fit/tidak fit.
 */
#[Layout('components.layout', ['title' => 'Antrian MCU Rekrutmen'])]
class QueuePage extends Component
{
    use HandlesMcuFileUpload;

    #[Url]
    public ?string $companyId = null;

    /** Stasiun yang modalnya sedang terbuka: perawat|lab|radiologi. Null = modal tertutup. */
    public ?string $station = null;

    public ?string $stationInviteeId = null;

    /** @var array<string, array{label:string, icon:string, perm:string, relation:string, modal:bool}> */
    private const STATIONS = [
        'perawat' => ['label' => 'Perawat', 'icon' => 'fa-heart-pulse', 'perm' => 'input_mcu_asesmen_perawat', 'relation' => 'nurseAssessment', 'modal' => true],
        'lab' => ['label' => 'Lab', 'icon' => 'fa-flask', 'perm' => 'input_mcu_laboratorium', 'relation' => 'labResult', 'modal' => true],
        'radiologi' => ['label' => 'Radiologi', 'icon' => 'fa-x-ray', 'perm' => 'input_mcu_radiologi', 'relation' => 'radiologyResult', 'modal' => true],
        'dokter' => ['label' => 'Dokter', 'icon' => 'fa-user-doctor', 'perm' => 'input_mcu_pemeriksaan_dokter', 'relation' => 'doctorExamination', 'modal' => false],
    ];

    public function mount(): void
    {
        abort_unless(Auth::user()->canAccessMcuModule(), 403);
    }

    public function getCompanyOptionsProperty(): Collection
    {
        return Company::where('requires_mcu', true)->orderBy('name')->pluck('name', 'id');
    }

    public function getAntrianProperty(): Collection
    {
        if (! $this->companyId) {
            return collect();
        }

        return JobApplication::query()
            ->whereHas('jobOpening', fn ($q) => $q->where('company_id', $this->companyId))
            ->where('status', 'mcu')
            ->whereHas('mcuInvitee')
            ->with(['answers', 'jobOpening:id,title', 'mcuInvitee.nurseAssessment', 'mcuInvitee.labResult', 'mcuInvitee.radiologyResult', 'mcuInvitee.doctorExamination'])
            ->latest()
            ->get();
    }

    /** @return array<int, array{key:string, label:string, icon:string, url:?string, modal:bool, done:bool}> */
    public function stationsFor(JobApplication $app): array
    {
        $user = Auth::user();
        $canEdit = $user->can(CustomPermissionEnum::EDIT_INPUT_MCU->value);
        $invitee = $app->mcuInvitee;
        $out = [];

        foreach (self::STATIONS as $key => $s) {
            if (! $user->can($s['perm']) && ! $canEdit) {
                continue;
            }

            $out[] = [
                'key' => $key,
                'label' => $s['label'],
                'icon' => $s['icon'],
                'url' => $s['modal'] ? null : route('rekrutmen.mcu.dokter', $invitee->id),
                'modal' => $s['modal'],
                'done' => (bool) $invitee->{$s['relation']},
                'inviteeId' => $invitee->id,
            ];
        }

        return $out;
    }

    public function getStationLabelProperty(): string
    {
        return self::STATIONS[$this->station]['label'] ?? '';
    }

    protected function stationPermission(): string
    {
        return self::STATIONS[$this->station]['perm'] ?? 'input_mcu_asesmen_perawat';
    }

    protected function rules(): array
    {
        return $this->fileRules();
    }

    public function openStation(string $inviteeId, string $station): void
    {
        abort_unless(isset(self::STATIONS[$station]) && self::STATIONS[$station]['modal'], 404);

        $this->station = $station;
        $this->stationInviteeId = $inviteeId;
        $this->file = null;
        $this->resetErrorBag();

        abort_unless(
            Auth::user()->can($this->stationPermission()) || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value),
            403
        );

        $invitee = $this->stationInvitee();
        $existing = $invitee->{self::STATIONS[$station]['relation']};

        // Data yang sudah diisi cuma boleh diubah oleh yang berwenang - pagar sama dengan halaman lama.
        abort_unless(! $existing || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value), 403, 'Data ini sudah diisi - hanya bisa diedit oleh yang berwenang.');

        $this->existingFilePath = $existing?->file_path;
    }

    public function closeStation(): void
    {
        $this->station = null;
        $this->stationInviteeId = null;
        $this->file = null;
        $this->existingFilePath = null;
        $this->resetErrorBag();
    }

    private function stationInvitee(): McuInvitee
    {
        $invitee = McuInvitee::with(['jobApplication.answers', 'nurseAssessment', 'labResult', 'radiologyResult'])
            ->findOrFail($this->stationInviteeId);

        abort_unless($invitee->jobApplication?->status === 'mcu', 403, 'Pelamar ini tidak sedang dalam tahap MCU.');

        return $invitee;
    }

    public function saveStation(): void
    {
        $this->validate();

        $invitee = $this->stationInvitee();
        $relation = self::STATIONS[$this->station]['relation'];

        abort_unless(
            ! $invitee->{$relation} || Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value),
            403
        );

        $path = $this->resolveFilePath($invitee->id);

        if (! $path) {
            $this->addError('file', 'Berkas hasil ' . $this->stationLabel . ' wajib diunggah.');

            return;
        }

        $invitee->{$relation}()->updateOrCreate(['mcu_invitee_id' => $invitee->id], ['file_path' => $path]);

        Notification::make()->title('Berkas ' . $this->stationLabel . ' tersimpan')->success()->send();

        $this->closeStation();
    }

    public function render()
    {
        return view('livewire.rekrutmen.mcu.queue-page');
    }
}
