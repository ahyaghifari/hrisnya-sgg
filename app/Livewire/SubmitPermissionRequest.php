<?php

namespace App\Livewire;

use App\Models\Master\Attendance\PermissionTypes;
use App\Services\Attendance\AttendaceReqService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SubmitPermissionRequest extends Component
{
    public ?string $permission_type_id = null;
    public ?string $date = null;
    public ?string $start_time = null;
    public ?string $end_time = null;
    public ?string $purpose = null;
    public ?string $location = null;

    public bool $with_substitute = false;
    public ?string $substitute_user_name = null;
    public ?string $substitute_task = null;

    public function mount(): void
    {
        abort_if(Auth::user()->canBrowseAllCompanies(), 403);
    }

    public function getPermissionTypesProperty()
    {
        return PermissionTypes::where('company_id', Auth::user()->company_id)->get();
    }

    public function toggleSubstitute(): void
    {
        $this->with_substitute = ! $this->with_substitute;

        if (! $this->with_substitute) {
            $this->substitute_user_name = null;
            $this->substitute_task = null;
        }
    }

    protected function rules(): array
    {
        return [
            'permission_type_id' => ['required', 'exists:m_permission_types,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'purpose' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'substitute_user_name' => ['nullable', 'string'],
            'substitute_task' => ['nullable', 'string'],
        ];
    }

    public function submit(): void
    {
        $data = $this->validate();

        try {
            app(AttendaceReqService::class)->storePermissionRequest([
                'type_id' => $data['permission_type_id'],
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'purpose' => $data['purpose'],
                'location' => $data['location'],
                'substitute_user_name' => $data['substitute_user_name'],
                'substitute_task' => $data['substitute_task'],
            ], Auth::user());

            Notification::make()
                ->title('Pengajuan izin berhasil dikirim')
                ->success()
                ->send();

            $this->redirect(route('my-attendance-requests'), navigate: true);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal mengajukan: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.submit-permission-request')
            ->layout('components.layout', ['title' => 'Pengajuan Izin']);
    }
}
