<?php

namespace App\Livewire;

use App\Models\Master\Attendance\LeaveTypes;
use App\Services\Attendance\AttendaceReqService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SubmitLeaveRequest extends Component
{
    public ?string $leave_type_id = null;
    public bool $is_one_day = true;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?string $reason = null;

    public ?array $balance = null;

    public function mount(): void
    {
        abort_if(Auth::user()->canBrowseAllCompanies(), 403);
    }

    public function getLeaveTypesProperty()
    {
        return LeaveTypes::where('company_id', Auth::user()->company_id)->get();
    }

    public function updatedLeaveTypeId(): void
    {
        $this->balance = $this->leave_type_id
            ? app(AttendaceReqService::class)->getLeaveUserBalance(Auth::id(), $this->leave_type_id)
            : null;
    }

    public function toggleOneDay(): void
    {
        $this->is_one_day = ! $this->is_one_day;
    }

    protected function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'exists:m_leave_types,id'],
            'is_one_day' => ['required', 'boolean'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required_if:is_one_day,false', 'nullable', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ];
    }

    public function submit(): void
    {
        $data = $this->validate();

        try {
            app(AttendaceReqService::class)->storeLeaveRequest([
                'type_id' => $data['leave_type_id'],
                'is_one_day' => $data['is_one_day'],
                'start_date' => $data['start_date'],
                'end_date' => $data['is_one_day'] ? $data['start_date'] : $data['end_date'],
            ], Auth::user());

            Notification::make()
                ->title('Pengajuan cuti berhasil dikirim')
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
        return view('livewire.submit-leave-request')
            ->layout('components.layout', ['title' => 'Pengajuan Cuti']);
    }
}
