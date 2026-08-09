<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\JobPositionReqStatus;
use App\Enum\ManpowerRequestType;
use App\Models\Job\ManpowerRequests;
use App\Models\Master\JobPositions;
use App\Services\JobPosition\ManPowerReqService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layout', ['title' => 'Ajukan Penambahan Kuota'])]
class CreateJabatanQuotaPage extends Component
{
    use WithFileUploads;

    public JobPositions $record;
    public ?ManpowerRequests $draft = null;

    public int $quantity = 1;
    public string $notes = '';
    public $file = null;

    public function mount(JobPositions $record): void
    {
        abort_unless(Auth::user()->can('Create:ManpowerRequests'), 403);
        abort_unless(
            Auth::user()->canFilterByCompany() || Auth::user()->company_id === $record->company_id,
            403
        );

        $this->record = $record;

        $this->draft = ManpowerRequests::query()
            ->where('job_position_id', $record->id)
            ->where('type', ManpowerRequestType::ADDITIONAL_QUOTA->value)
            ->where('status', JobPositionReqStatus::DRAFT->value)
            ->where('requested_by', Auth::id())
            ->latest()
            ->first();

        if ($this->draft) {
            $this->quantity = $this->draft->quantity;
            $this->notes = $this->draft->notes ?? '';
        }
    }

    protected function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'],
        ];
    }

    protected function submitRequest(bool $submit): void
    {
        $data = $this->validate();
        unset($data['file']);

        $data['company_id'] = $this->record->company_id;
        $data['job_position_id'] = $this->record->id;
        $data['type'] = ManpowerRequestType::ADDITIONAL_QUOTA->value;

        if ($this->file) {
            $data['file_path'] = $this->file->store('manpower-requests/' . $this->record->id . '/additional_quota', 'public');
        }

        try {
            if ($this->draft) {
                app(ManPowerReqService::class)->updateDraft($this->draft, $data, $submit);
            } else {
                app(ManPowerReqService::class)->create($data, $submit);
            }

            Notification::make()
                ->title($submit ? 'Pengajuan kuota berhasil diajukan' : 'Draft berhasil disimpan')
                ->success()
                ->send();

            $this->redirect(route('kepegawaian.jabatan'), navigate: true);
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

        Notification::make()->title('Draft pengajuan kuota dibatalkan')->success()->send();

        $this->redirect(route('kepegawaian.jabatan'), navigate: true);
    }

    public function render()
    {
        return view('livewire.kepegawaian.create-jabatan-quota-page');
    }
}
