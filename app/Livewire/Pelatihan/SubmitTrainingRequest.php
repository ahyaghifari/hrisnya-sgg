<?php

namespace App\Livewire\Pelatihan;

use App\Enum\CustomPermissionEnum;
use App\Services\LMS\TrainingRequestService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Pengajuan Pelatihan'])]
class SubmitTrainingRequest extends Component
{
    public ?string $title = null;
    public ?string $organizer = null;
    public ?string $description = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?string $estimated_cost = null;

    public function mount(): void
    {
        abort_if(Auth::user()->canBrowseAllCompanies(), 403);
        abort_unless(Auth::user()->can(CustomPermissionEnum::AJUKAN_PELATIHAN->value), 403);
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function submit(): void
    {
        $data = $this->validate();

        try {
            app(TrainingRequestService::class)->store($data);

            Notification::make()
                ->title('Pengajuan pelatihan berhasil dikirim')
                ->success()
                ->send();

            $this->redirect(route('pelatihan.pengajuan-saya'), navigate: true);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal mengajukan: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.pelatihan.submit-training-request');
    }
}
