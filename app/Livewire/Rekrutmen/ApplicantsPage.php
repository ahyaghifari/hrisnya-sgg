<?php

namespace App\Livewire\Rekrutmen;

use App\Models\Job\JobOpenings;
use App\Services\Job\RekrutmenSyncService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Daftar pelamar 1 lowongan — data live dari project Rekrutmen eksternal
 * (RekrutmenSyncService::getJobOpeningApplications). READ-ONLY (Fase 2). Aksi ubah status
 * menyusul Fase 3. API mati → empty state, halaman tetap render.
 */
#[Layout('components.layout', ['title' => 'Daftar Pelamar'])]
class ApplicantsPage extends Component
{
    public JobOpenings $record;

    #[Url]
    public string $status = '';

    public function mount(JobOpenings $record): void
    {
        abort_unless(Auth::user()->can('View:JobOpenings'), 403);

        abort_unless(
            Auth::user()->canFilterByCompany() || $record->company_id === Auth::user()->company_id,
            403,
        );

        $record->load(['jobPosition.orgStructure', 'company']);
        $this->record = $record;
    }

    /** @return list<array{job_application_id:string,status:string,applicant_name:?string,applicant_email:?string,updated_at:?string}> */
    public function getApplicantsProperty(): array
    {
        return app(RekrutmenSyncService::class)
            ->getJobOpeningApplications($this->record->id, $this->status ?: null);
    }

    public function getStatusOptionsProperty(): array
    {
        return [
            'applied' => 'Lamaran Baru', 'screening' => 'Seleksi Berkas', 'shortlisted' => 'Lolos Berkas / Tes',
            'interview' => 'Wawancara', 'offering' => 'Offering', 'mcu' => 'MCU',
            'onboarding' => 'Onboarding', 'passed' => 'Diterima', 'rejected' => 'Ditolak',
        ];
    }

    public function render()
    {
        return view('livewire.rekrutmen.applicants-page');
    }
}
