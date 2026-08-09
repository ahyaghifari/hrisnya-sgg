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
 * Simpan Draft (field saja, status tidak berubah), Buka Lowongan (publish, tampil di sistem Rekrutmen),
 * Tutup Lowongan (kalau sedang dibuka).
 */
#[Layout('components.layout', ['title' => 'Edit Lowongan Kerja'])]
class EditJobOpeningPage extends Component
{
    public JobOpenings $record;

    public ?string $company_id = null;
    public ?string $job_position_id = null;
    public string $alias = '';
    public int $quota = 1;
    public ?int $max_applicants = null;
    public ?string $application_deadline = null;
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
        $this->alias = (string) $record->alias;
        $this->quota = $record->quota;
        $this->max_applicants = $record->max_applicants;
        $this->application_deadline = $record->application_deadline?->format('Y-m-d');
        $this->description = (string) $record->description;
        $this->requirements = (string) $record->requirements;
    }

    /** Ganti posisi jabatan -> isi ulang deskripsi/persyaratan dari master jabatan baru, tapi
     * cuma kalau field masih kosong (jangan timpa yang sudah diedit manual). */
    public function updatedJobPositionId(): void
    {
        if ($this->description === '' && $this->requirements === '') {
            $jobPosition = JobPositions::find($this->job_position_id);
            $this->description = (string) $jobPosition?->description;
            $this->requirements = (string) $jobPosition?->qualification;
        }
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

    /** Kuota SENGAJA tidak masuk sini - mengikuti quantity dari Permintaan Tenaga Kerja yang
     * disetujui (sudah benar sejak dibuat di ManPowerReqService::approveManPower()), tidak boleh
     * diedit bebas di sini biar tidak menyimpang dari yang sudah disetujui approver. Property
     * $quota tetap ada buat ditampilkan (read-only), cuma tidak divalidasi/disimpan dari form. */
    protected function rules(): array
    {
        return [
            'job_position_id' => ['required', 'exists:m_job_positions,id'],
            'alias' => ['nullable', 'string', 'max:255'],
            'max_applicants' => ['nullable', 'integer', 'min:1'],
            'application_deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
        ];
    }

    public function saveDraft(): void
    {
        $data = $this->validate();

        app(JobOpeningService::class)->saveDraft($this->record, $data);

        $title = $this->status === JobOpeningStatus::WAITING
            ? 'Draft lowongan kerja disimpan'
            : 'Perubahan disimpan — ikut ke-update di sistem Rekrutmen';

        Notification::make()->title($title)->success()->send();

        $this->redirect(route('kepegawaian.lowongan.show', $this->record), navigate: true);
    }

    public function publish(): void
    {
        $data = $this->validate();

        app(JobOpeningService::class)->publish($this->record, $data);

        Notification::make()->title('Lowongan kerja dibuka — sekarang tampil di sistem Rekrutmen')->success()->send();

        $this->redirect(route('kepegawaian.lowongan.show', $this->record), navigate: true);
    }

    public function close(): void
    {
        abort_unless($this->status === JobOpeningStatus::OPEN, 403);

        app(JobOpeningService::class)->close($this->record);

        Notification::make()->title('Lowongan kerja ditutup')->success()->send();

        $this->redirect(route('kepegawaian.lowongan.show', $this->record), navigate: true);
    }

    public function render()
    {
        return view('livewire.rekrutmen.edit-job-opening-page');
    }
}
