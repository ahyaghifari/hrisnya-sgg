<?php

namespace App\Livewire;

use App\Models\Master\Attendance\OvertimeTypes;
use App\Services\Attendance\AttendaceReqService;
use App\Support\TimeInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubmitOvertimeRequest extends Component
{
    use WithFileUploads;

    public ?string $overtime_type_id = null;
    public ?string $date = null;

    /** @var array<int, array{task_description: ?string, start_time: ?string, end_time: ?string}> */
    public array $tasks = [
        ['task_description' => null, 'start_time' => null, 'end_time' => null],
    ];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    public function mount(): void
    {
        abort_if(Auth::user()->canBrowseAllCompanies(), 403);
    }

    public function getOvertimeTypesProperty()
    {
        return OvertimeTypes::where('company_id', Auth::user()->company_id)->get();
    }

    public function addTask(): void
    {
        $this->tasks[] = ['task_description' => null, 'start_time' => null, 'end_time' => null];
    }

    public function removeTask(int $index): void
    {
        if (count($this->tasks) <= 1) {
            return;
        }

        unset($this->tasks[$index]);
        $this->tasks = array_values($this->tasks);
    }

    protected function rules(): array
    {
        return [
            'overtime_type_id' => ['required', 'exists:m_overtime_types,id'],
            'date' => ['required', 'date'],
            'tasks' => ['nullable', 'array'],
            'tasks.*.task_description' => ['nullable', 'string', 'max:500'],
            'tasks.*.start_time' => ['nullable', 'date_format:H:i'],
            'tasks.*.end_time' => ['nullable', 'date_format:H:i', 'after:tasks.*.start_time'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function submit(): void
    {
        foreach ($this->tasks as $i => $task) {
            $this->tasks[$i]['start_time'] = TimeInput::normalize($task['start_time'] ?? null);
            $this->tasks[$i]['end_time'] = TimeInput::normalize($task['end_time'] ?? null);
        }

        $data = $this->validate();

        try {
            app(AttendaceReqService::class)->storeOvertimeRequest([
                'type_id' => $data['overtime_type_id'],
                'date' => $data['date'],
                'tasks' => $data['tasks'],
                'attachments' => $data['attachments'] ?? null,
            ], Auth::user());

            Notification::make()
                ->title('Pengajuan lembur berhasil dikirim')
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
        return view('livewire.submit-overtime-request')
            ->layout('components.layout', ['title' => 'Pengajuan Lembur']);
    }
}
