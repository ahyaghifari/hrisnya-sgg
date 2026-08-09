<?php

namespace App\Livewire\Rekrutmen;

use App\Models\Job\JobOpenings;
use App\Models\Master\Companies;
use App\Services\Job\RekrutmenSyncService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * List Lowongan Kerja versi portal — padanan JobOpeningResource admin.
 */
#[Layout('components.layout', ['title' => 'Lowongan Kerja'])]
class JobOpeningsPage extends Component
{
    use WithPagination;

    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public ?string $companyId = null;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('ViewAny:JobOpenings'), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    /** Tombol sederhana ngetes konektivitas ke sistem Rekrutmen eksternal (GET /hris-test) -
     * lihat RekrutmenSyncService::testConnection() & planning/2026-08-09-integrasi-job-opening-ke-rekrutmen.md. */
    public function testRekrutmenConnection(): void
    {
        $result = app(RekrutmenSyncService::class)->testConnection();

        Notification::make()
            ->title($result['ok'] ? 'Terhubung' : 'Gagal terhubung')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    protected function baseQuery()
    {
        $search = trim($this->search);

        return JobOpenings::query()
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->when($search !== '', fn ($q) => $q->whereHas('jobPosition', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->with(['jobPosition.orgStructure', 'company']);
    }

    public function getJobOpeningsProperty()
    {
        return $this->baseQuery()->latest()->paginate(10)->appends([
            'companyId' => $this->companyId,
            'search' => $this->search,
        ]);
    }

    /** Jumlah pelamar per lowongan — SATU-SATUNYA sumber sekarang di halaman ini adalah sistem
     * Rekrutmen (batch, 1 panggilan buat semua baris di halaman, bukan N+1 per baris).
     * `applicants_count` lokal HRIS (CandidateJobApplications, panel /candidate HRIS sendiri)
     * sengaja tidak lagi ditampilkan di sini — ke depannya rekrutmen semua lewat sistem
     * Rekrutmen eksternal, panel /candidate HRIS bukan jalur utama lagi. */
    public function getRekrutmenApplicantCountsProperty(): array
    {
        $ids = $this->jobOpenings->pluck('id')->all();

        return app(RekrutmenSyncService::class)->getApplicantCounts($ids);
    }

    public function render()
    {
        return view('livewire.rekrutmen.job-openings-page');
    }
}
