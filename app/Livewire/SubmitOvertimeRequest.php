<?php

namespace App\Livewire;

use App\Enum\Attendance\OvertimeTypeEnum;
use App\Services\Attendance\AttendaceReqService;
use App\Support\TimeInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubmitOvertimeRequest extends Component
{
    use WithFileUploads;

    public ?string $overtime_type = null; // key OvertimeTypeEnum (biasa|khusus)
    public bool $submitted = false; // true sesudah sukses, sampai redirect selesai (cegah kirim ganda)
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

    /** @return array<int, OvertimeTypeEnum> */
    public function getOvertimeTypesProperty(): array
    {
        return OvertimeTypeEnum::cases();
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
            'overtime_type' => ['required', \Illuminate\Validation\Rule::enum(OvertimeTypeEnum::class)],
            'date' => ['required', 'date'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.task_description' => ['required', 'string', 'max:500'],
            'tasks.*.start_time' => ['required', 'date_format:H:i'],
            'tasks.*.end_time' => ['required', 'date_format:H:i', 'after:tasks.*.start_time'],
            'attachments' => ['nullable', 'array', 'max:' . \App\Models\Attendance\AttendanceRequestAttachments::MAX_OVERTIME],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'tasks.*.task_description' => 'deskripsi tugas',
            'tasks.*.start_time' => 'jam mulai',
            'tasks.*.end_time' => 'jam selesai',
        ];
    }

    public function submit(): void
    {
        if ($this->submitted) {
            return;
        }

        foreach ($this->tasks as $i => $task) {
            $this->tasks[$i]['start_time'] = TimeInput::normalize($task['start_time'] ?? null);
            $this->tasks[$i]['end_time'] = TimeInput::normalize($task['end_time'] ?? null);
        }

        $data = $this->validate();

        try {
            app(AttendaceReqService::class)->storeOvertimeRequest([
                'type_id' => $data['overtime_type'],
                'date' => $data['date'],
                'tasks' => $data['tasks'],
                'attachments' => $data['attachments'] ?? null,
            ], Auth::user());

            Notification::make()
                ->title('Pengajuan lembur berhasil dikirim')
                ->success()
                ->send();

            // Tombol tetap terkunci sampai halaman berpindah - respons sukses sudah selesai (loading
            // berakhir) tapi navigasi ke daftar pengajuan belum, jangan sampai bisa diklik lagi.
            $this->submitted = true;

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
