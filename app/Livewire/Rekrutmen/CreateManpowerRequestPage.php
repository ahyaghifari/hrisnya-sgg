<?php

namespace App\Livewire\Rekrutmen;

use App\Enum\JobPositionReqStatus;
use App\Enum\ManpowerRequestType;
use App\Models\Job\ManpowerRequests;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Services\JobPosition\ManPowerReqService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layout', ['title' => 'Ajukan Permintaan Tenaga Kerja'])]
class CreateManpowerRequestPage extends Component
{
    use WithFileUploads;

    public ?ManpowerRequests $draft = null;

    public ?string $companyId = null;
    public ?string $job_position_id = null;
    public string $type = '';
    public int $quantity = 1;
    public string $notes = '';
    public $file = null;

    public function mount(?ManpowerRequests $draft = null): void
    {
        abort_unless(Auth::user()->can('Create:ManpowerRequests'), 403);

        if ($draft) {
            abort_unless($draft->status === JobPositionReqStatus::DRAFT->value, 404);
            abort_unless($draft->requested_by === Auth::id(), 403);

            $this->draft = $draft;
            $this->companyId = $draft->company_id;
            $this->job_position_id = $draft->job_position_id;
            $this->type = $draft->type;
            $this->quantity = $draft->quantity;
            $this->notes = $draft->notes ?? '';

            return;
        }

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->job_position_id = null;
    }

    public function updatedType(): void
    {
        // Opsi Jabatan beda per tipe (Tambah Karyawan cuma jabatan yang kurang kuota,
        // Penggantian bebas semua) - reset biar tidak nyangkut pilihan yang sudah tidak valid.
        $this->job_position_id = null;
        $this->quantity = 1;
    }

    public function updatedJobPositionId(): void
    {
        // Penambahan Karyawan: jumlah dikunci = persis kekurangan kuota, bukan pilihan bebas.
        // Penggantian Karyawan: jumlah bebas diisi user (untuk sementara, belum ada pembatasan).
        if ($this->type === ManpowerRequestType::ADDITIONAL_EMPLOYEE->value) {
            $this->quantity = max($this->availableQuota, 1);
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

    public function getTypeOptionsProperty(): array
    {
        return collect(ManpowerRequestType::cases())
            ->reject(fn (ManpowerRequestType $type) => $type === ManpowerRequestType::ADDITIONAL_QUOTA)
            ->mapWithKeys(fn (ManpowerRequestType $type) => [$type->value => $type->getLabel()])
            ->all();
    }

    public function getJobPositionOptionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        $query = JobPositions::where('company_id', $this->companyId);

        // Penambahan Karyawan cuma boleh ke jabatan yang sudah approved DAN beneran kekurangan
        // orang (actual_manpower > jumlah karyawan terisi) - Penggantian Karyawan bebas semua
        // jabatan (belum ada pembatasan, sifatnya ganti orang lama bukan nambah slot).
        if ($this->type === ManpowerRequestType::ADDITIONAL_EMPLOYEE->value) {
            return $query->where('status', JobPositionReqStatus::APPROVED->value)
                ->withCount('userAssignments')
                ->get()
                ->filter(fn (JobPositions $jp) => ($jp->actual_manpower ?? 0) > $jp->user_assignments_count)
                ->pluck('name', 'id');
        }

        return $query->pluck('name', 'id');
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

        // Penambahan Karyawan: jumlah dikunci sama persis kekurangan kuota (field disabled di
        // UI), validasi server-side jaga-jaga tetap dipasang biar tidak bisa di-tamper.
        // Penggantian Karyawan: bebas, belum ada pembatasan (instruksi user 2026-08-09).
        if ($this->type === ManpowerRequestType::ADDITIONAL_EMPLOYEE->value) {
            $rules['quantity'][] = 'in:' . max($this->availableQuota, 1);
        }

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
            if ($this->draft) {
                app(ManPowerReqService::class)->updateDraft($this->draft, $data, $submit);
            } else {
                app(ManPowerReqService::class)->create($data, $submit);
            }

            Notification::make()
                ->title($submit ? 'Permintaan berhasil diajukan' : 'Draft berhasil disimpan')
                ->success()
                ->send();

            $this->redirect(route('kepegawaian.permintaan'), navigate: true);
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

    /** Draft belum pernah masuk Approval Engine (snapshot cuma dibuat saat submit) - aman
     * dihapus langsung, tidak ada approval yang perlu dibatalkan. Model pakai SoftDeletes. */
    public function discardDraft(): void
    {
        abort_unless($this->draft, 404);

        $this->draft->delete();

        Notification::make()->title('Draft permintaan dibatalkan')->success()->send();

        $this->redirect(route('kepegawaian.permintaan'), navigate: true);
    }

    public function render()
    {
        return view('livewire.rekrutmen.create-manpower-request-page');
    }
}
