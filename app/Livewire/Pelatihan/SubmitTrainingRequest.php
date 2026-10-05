<?php

namespace App\Livewire\Pelatihan;

use App\Enum\CustomPermissionEnum;
use App\Models\LMS\TrainingClaim;
use App\Services\LMS\TrainingClaimService;
use App\Services\LMS\TrainingRequestService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * 1 halaman, 2 jenis pengajuan lewat $jenis:
 *  - 'sudah' (default): pelatihan SUDAH terlaksana -> sertifikat + jam per hari, HC periksa
 *    konversi ke JP (TrainingClaim).
 *  - 'belum': pelatihan belum terlaksana -> pengajuan biaya + approval atasan + ikatan dinas
 *    (TrainingRequests).
 */
#[Layout('components.layout', ['title' => 'Pengajuan Pelatihan'])]
class SubmitTrainingRequest extends Component
{
    use WithFileUploads;

    public string $jenis = 'sudah';

    public ?string $title = null;
    public ?string $organizer = null;

    // jenis 'belum'
    public ?string $description = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?string $estimated_cost = null;

    // jenis 'sudah'
    public $certificate = null;
    public array $sessions = [['date' => null, 'start_time' => null, 'end_time' => null]];

    public function mount(): void
    {
        abort_if(Auth::user()->canBrowseAllCompanies(), 403);
        abort_unless(Auth::user()->can(CustomPermissionEnum::AJUKAN_PELATIHAN->value), 403);
    }

    public function updatedJenis(): void
    {
        $this->resetErrorBag();
    }

    protected function rules(): array
    {
        $shared = [
            'title' => ['required', 'string', 'max:255'],
            'organizer' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->jenis === 'sudah') {
            return $shared + [
                'certificate' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
                'sessions' => ['required', 'array', 'min:1'],
                'sessions.*.date' => ['required', 'date'],
                'sessions.*.start_time' => ['required', 'date_format:H:i'],
                'sessions.*.end_time' => ['required', 'date_format:H:i', 'after:sessions.*.start_time'],
            ];
        }

        return $shared + [
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'certificate' => 'sertifikat',
            'sessions.*.date' => 'tanggal',
            'sessions.*.start_time' => 'jam mulai',
            'sessions.*.end_time' => 'jam selesai',
        ];
    }

    public function addSession(): void
    {
        $this->sessions[] = ['date' => null, 'start_time' => null, 'end_time' => null];
    }

    public function removeSession(int $index): void
    {
        if (count($this->sessions) > 1) {
            unset($this->sessions[$index]);
            $this->sessions = array_values($this->sessions);
        }
    }

    public function getTotalMinutesProperty(): int
    {
        return TrainingClaim::minutesOf($this->sessions);
    }

    public function getPreviewJpProperty(): int
    {
        return TrainingClaim::jpFor($this->totalMinutes);
    }

    public function submit(): void
    {
        $data = $this->validate();

        if ($this->jenis === 'sudah') {
            $this->submitTerlaksana($data);

            return;
        }

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

    private function submitTerlaksana(array $data): void
    {
        try {
            app(TrainingClaimService::class)->store($data, $this->certificate);

            Notification::make()->title('Pengajuan pelatihan terlaksana berhasil dikirim')->success()->send();

            $this->redirect(route('pelatihan.klaim'), navigate: true);
        } catch (\Exception $e) {
            Notification::make()->title('Gagal mengajukan: ' . $e->getMessage())->danger()->send();
        }
    }

    public function render()
    {
        return view('livewire.pelatihan.submit-training-request');
    }
}
