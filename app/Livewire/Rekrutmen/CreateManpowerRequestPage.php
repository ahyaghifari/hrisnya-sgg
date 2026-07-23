<?php

namespace App\Livewire\Rekrutmen;

use App\Enum\ManpowerRequestType;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Services\JobPosition\ManPowerReqService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Form Ajukan Permintaan Tenaga Kerja — padanan CreateManpowerRequest admin. Beda dari admin:
 * `job_position_id` difilter per company (bug lintas company di admin, lihat
 * planning/eager-pondering-turtle.md), dan 2 tombol aksi eksplisit (submit / simpan draft).
 */
#[Layout('components.layout', ['title' => 'Ajukan Permintaan Tenaga Kerja'])]
class CreateManpowerRequestPage extends Component
{
    use WithFileUploads;

    public ?string $companyId = null;
    public string $tab = 'quota';
    public ?string $job_position_id = null;
    public string $type = '';
    public int $quantity = 1;
    public string $notes = '';
    public $file = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('Create:ManpowerRequests'), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $tab = request()->query('tab', 'quota');
        $this->tab = in_array($tab, ['quota', 'employee_change'], true) ? $tab : 'quota';
        $this->type = $this->tab === 'quota' ? ManpowerRequestType::ADDITIONAL_QUOTA->value : '';
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->job_position_id = null;
    }

    public function updatedJobPositionId(): void
    {
        $this->quantity = min($this->quantity, max($this->availableQuota, 0)) ?: 1;
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
        if (! $this->companyId) {
            return collect();
        }

        return JobPositions::where('company_id', $this->companyId)->pluck('name', 'id');
    }

    public function getSelectedJobPositionProperty(): ?JobPositions
    {
        return $this->job_position_id ? JobPositions::find($this->job_position_id) : null;
    }

    public function getAvailableQuotaProperty(): int
    {
        $jobPosition = $this->selectedJobPosition;

        if (! $jobPosition) {
            return 0;
        }

        return $jobPosition->actual_manpower - $jobPosition->userAssignments()->count();
    }

    protected function rules(): array
    {
        $rules = [
            'job_position_id' => ['required', 'exists:m_job_positions,id'],
            'type' => ['required', 'in:' . implode(',', array_column(ManpowerRequestType::cases(), 'value'))],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'],
        ];

        if ($this->tab === 'quota') {
            return $rules;
        }

        $rules['quantity'][] = 'max:' . max($this->availableQuota, 1);

        return $rules;
    }

    protected function submitRequest(bool $submit): void
    {
        $data = $this->validate();
        unset($data['file']);

        $data['company_id'] = $this->companyId ?? Auth::user()->company_id;

        if ($this->file) {
            $data['file_path'] = $this->file->store('manpower-requests/' . $this->job_position_id . '/' . $this->type, 'public');
        }

        try {
            app(ManPowerReqService::class)->create($data, $submit);

            Notification::make()
                ->title($submit ? 'Permintaan berhasil diajukan' : 'Draft berhasil disimpan')
                ->success()
                ->send();

            $this->redirect(route('rekrutmen.permintaan-tenaga-kerja', ['tab' => $this->tab]), navigate: true);
        } catch (\Exception $e) {
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }

    public function submit(): void
    {
        $this->submitRequest(true);
    }

    public function saveDraft(): void
    {
        $this->submitRequest(false);
    }

    public function render()
    {
        return view('livewire.rekrutmen.create-manpower-request-page');
    }
}
