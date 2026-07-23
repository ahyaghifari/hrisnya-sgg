<?php

namespace App\Livewire\Rekrutmen;

use App\Enum\Recruitment\JobOpeningStatus;
use App\Models\Job\JobOpenings;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Services\Job\JobOpeningService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Form Edit Lowongan Kerja — SENGAJA tidak ada create manual. Lowongan cuma bisa muncul lewat
 * approval Permintaan Tenaga Kerja (`ManPowerReqService::approveManPower()`, auto-create
 * `is_active=false`, `opened_at=null` = "Menunggu Dibuka"). Halaman ini kelola siklus hidupnya:
 * Simpan Draft (field saja, status tidak berubah), Buka Lowongan (publish, tampil ke kandidat),
 * Tutup Lowongan (kalau sedang dibuka).
 */
#[Layout('components.layout', ['title' => 'Edit Lowongan Kerja'])]
class EditJobOpeningPage extends Component
{
    public JobOpenings $record;

    public ?string $company_id = null;
    public ?string $job_position_id = null;
    public int $quota = 1;
    public string $description = '';
    public string $requirements = '';

    public function mount(JobOpenings $record): void
    {
        abort_unless(Auth::user()->can('Update:JobOpenings'), 403);

        abort_unless(
            Auth::user()->canFilterByCompany() || $record->company_id === Auth::user()->company_id,
            403
        );

        $this->record = $record;
        $this->company_id = $record->company_id;
        $this->job_position_id = $record->job_position_id;
        $this->quota = $record->quota;
        $this->description = (string) $record->description;
        $this->requirements = (string) $record->requirements;
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function getJobPositionOptionsProperty()
    {
        if (! $this->company_id) {
            return collect();
        }

        return JobPositions::where('company_id', $this->company_id)->pluck('name', 'id');
    }

    public function getStatusProperty(): JobOpeningStatus
    {
        return $this->record->openingStatus();
    }

    protected function rules(): array
    {
        return [
            'job_position_id' => ['required', 'exists:m_job_positions,id'],
            'quota' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
        ];
    }

    public function saveDraft(): void
    {
        $data = $this->validate();

        app(JobOpeningService::class)->saveDraft($this->record, $data);

        Notification::make()->title('Draft lowongan kerja disimpan')->success()->send();

        $this->redirect(route('rekrutmen.lowongan.show', $this->record), navigate: true);
    }

    public function publish(): void
    {
        $data = $this->validate();

        app(JobOpeningService::class)->publish($this->record, $data);

        Notification::make()->title('Lowongan kerja dibuka — sekarang tampil ke kandidat')->success()->send();

        $this->redirect(route('rekrutmen.lowongan.show', $this->record), navigate: true);
    }

    public function close(): void
    {
        abort_unless($this->status === JobOpeningStatus::OPEN, 403);

        app(JobOpeningService::class)->close($this->record);

        Notification::make()->title('Lowongan kerja ditutup')->success()->send();

        $this->redirect(route('rekrutmen.lowongan.show', $this->record), navigate: true);
    }

    public function render()
    {
        return view('livewire.rekrutmen.edit-job-opening-page');
    }
}
