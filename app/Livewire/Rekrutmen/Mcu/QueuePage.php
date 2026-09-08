<?php

namespace App\Livewire\Rekrutmen\Mcu;

use App\Enum\CustomPermissionEnum;
use App\Models\Rekrutmen\Company;
use App\Models\Rekrutmen\JobApplication;
use App\Models\Rekrutmen\JobOpening;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Antrian MCU Rekrutmen — pengganti App\Filament\Rekrutmen\Pages\McuStationPage (dulu halaman
 * panel Filament), dipindah ke portal supaya staf klinis tidak perlu akses panel /rekrutmen.
 * Pilih perusahaan -> lowongan, lalu isi per stasiun sesuai permission INPUT_MCU_*. Upload
 * berkas belum ada (disk `rekrutmen` belum shared) — hasil dicatat teks, sama seperti dulu.
 */
#[Layout('components.layout', ['title' => 'Antrian MCU Rekrutmen'])]
class QueuePage extends Component
{
    #[Url]
    public ?string $companyId = null;

    #[Url]
    public ?string $jobOpeningId = null;

    /** @var array<int, array{key:string, label:string, icon:string, perm:string, relation:string, route:string}> */
    private const STATIONS = [
        ['key' => 'perawat', 'label' => 'Perawat', 'icon' => 'fa-heart-pulse', 'perm' => 'input_mcu_asesmen_perawat', 'relation' => 'nurseAssessment', 'route' => 'rekrutmen.mcu.perawat'],
        ['key' => 'lab', 'label' => 'Lab', 'icon' => 'fa-flask', 'perm' => 'input_mcu_laboratorium', 'relation' => 'labResult', 'route' => 'rekrutmen.mcu.laboratorium'],
        ['key' => 'radiologi', 'label' => 'Radiologi', 'icon' => 'fa-x-ray', 'perm' => 'input_mcu_radiologi', 'relation' => 'radiologyResult', 'route' => 'rekrutmen.mcu.radiologi'],
        ['key' => 'dokter', 'label' => 'Dokter', 'icon' => 'fa-user-doctor', 'perm' => 'input_mcu_pemeriksaan_dokter', 'relation' => 'doctorExamination', 'route' => 'rekrutmen.mcu.dokter'],
    ];

    public function mount(): void
    {
        abort_unless(Auth::user()->canAccessMcuModule(), 403);
    }

    public function updatedCompanyId(): void
    {
        $this->jobOpeningId = null;
    }

    public function getCompanyOptionsProperty(): Collection
    {
        return Company::where('requires_mcu', true)->orderBy('name')->pluck('name', 'id');
    }

    public function getJobOpeningOptionsProperty(): Collection
    {
        return $this->companyId
            ? JobOpening::where('company_id', $this->companyId)->latest()->pluck('title', 'id')
            : collect();
    }

    public function getAntrianProperty(): Collection
    {
        if (! $this->jobOpeningId) {
            return collect();
        }

        return JobApplication::query()
            ->where('job_opening_id', $this->jobOpeningId)
            ->where('status', 'mcu')
            ->whereHas('mcuInvitee')
            ->with(['answers', 'mcuInvitee.nurseAssessment', 'mcuInvitee.labResult', 'mcuInvitee.radiologyResult', 'mcuInvitee.doctorExamination'])
            ->latest()
            ->get();
    }

    /** @return array<int, array{label:string, icon:string, url:string, done:bool}> */
    public function stationsFor(JobApplication $app): array
    {
        $user = Auth::user();
        $canEdit = $user->can(CustomPermissionEnum::EDIT_INPUT_MCU->value);
        $invitee = $app->mcuInvitee;
        $out = [];

        foreach (self::STATIONS as $s) {
            if (! $user->can($s['perm']) && ! $canEdit) {
                continue;
            }

            $out[] = [
                'label' => $s['label'],
                'icon' => $s['icon'],
                'url' => route($s['route'], $invitee->id),
                'done' => (bool) $invitee->{$s['relation']},
            ];
        }

        return $out;
    }

    public function render()
    {
        return view('livewire.rekrutmen.mcu.queue-page');
    }
}
