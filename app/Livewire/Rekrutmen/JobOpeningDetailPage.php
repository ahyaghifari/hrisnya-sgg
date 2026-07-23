<?php

namespace App\Livewire\Rekrutmen;

use App\Models\Job\JobOpenings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail Lowongan Kerja + daftar pelamar — padanan ViewJobOpening + ApplicantRelationManager
 * admin. Edit lowongan diarahkan ke EditJobOpeningPage, lihat kandidat ke
 * CandidateApplicationDetailPage. Tidak ada tombol create manual — lowongan cuma muncul dari
 * approval Permintaan Tenaga Kerja.
 */
#[Layout('components.layout', ['title' => 'Detail Lowongan Kerja'])]
class JobOpeningDetailPage extends Component
{
    public JobOpenings $record;

    public function mount(JobOpenings $record): void
    {
        abort_unless(Auth::user()->can('View:JobOpenings'), 403);

        abort_unless(
            Auth::user()->canFilterByCompany() || $record->company_id === Auth::user()->company_id,
            403
        );

        $record->load(['jobPosition.orgStructure', 'company', 'applicants.candidate']);

        $this->record = $record;
    }

    public function getCanEditProperty(): bool
    {
        return Auth::user()->can('Update:JobOpenings');
    }

    public function render()
    {
        return view('livewire.rekrutmen.job-opening-detail-page');
    }
}
