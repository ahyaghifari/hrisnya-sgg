<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Enum\JobPositionReqStatus;
use App\Livewire\Kepegawaian\Concerns\HasJabatanForm;
use App\Models\Master\JobPositions;
use App\Services\JobPosition\JobPositionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "Ajukan Jabatan Baru" - padanan CreateJobPosition admin, tapi eksplisit 2 tombol (Simpan
 * Draft / Ajukan, sama pola CreateManpowerRequestPage) - beda dari admin yang cuma bisa
 * "Create" mentah (submit ke approval flow JobPositionService::requestJobPosition() sengaja
 * ditunda di admin, lihat komentar CreateJobPosition::mutateFormDataBeforeCreate()). Di
 * portal ini justru intinya: submit langsung minta approve JOBPOSITION_REQUEST.
 */
#[Layout('components.layout', ['title' => 'Ajukan Jabatan Baru'])]
class CreateJabatanPage extends Component
{
    use HasJabatanForm;
    use WithFileUploads;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->company_id = Auth::user()->company_id;
        }
    }

    protected function submitJabatan(bool $submit): void
    {
        $data = $this->validate($this->jabatanRules());
        unset($data['department_id'], $data['division_id'], $data['sub_division_id']);

        $payload = $this->jabatanPayload();
        $payload['status'] = JobPositionReqStatus::DRAFT->value;

        try {
            $jobPosition = JobPositions::create($payload);

            if ($submit) {
                app(JobPositionService::class)->requestJobPosition($jobPosition, JobPositionReqStatus::REQUESTED->value);
            }

            Notification::make()
                ->title($submit ? 'Pengajuan jabatan berhasil dikirim' : 'Draft jabatan berhasil disimpan')
                ->success()
                ->send();

            $this->redirect(route('kepegawaian.jabatan'), navigate: true);
        } catch (\Exception $e) {
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }

    public function submit(): void
    {
        $this->submitJabatan(true);
    }

    public function saveDraft(): void
    {
        $this->submitJabatan(false);
    }

    public function render()
    {
        return view('livewire.kepegawaian.create-jabatan-page');
    }
}
