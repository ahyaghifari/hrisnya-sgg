<?php

namespace App\Livewire\Coaching;

use App\Models\Master\Companies;
use App\Models\User;
use App\Services\Coaching\CoachingService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Form Buat Coaching baru (HC) — padanan CreateCoaching admin, satu langkah langsung isi semua
 * field (status jadi IN_PROGRESS), bukan lewat state PENDING dulu.
 */
#[Layout('components.layout', ['title' => 'Buat Coaching'])]
class CreateCoachingPage extends Component
{
    public ?string $companyId = null;
    public ?string $user_id = null;
    public ?string $coach_by = null;
    public string $title = '';
    public ?string $improvement_due_date = null;
    public string $goals = '';
    public string $employee_condition = '';
    public string $solution = '';
    public string $improvement_action = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('Create:Coaching'), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->coach_by = Auth::id();
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->user_id = null;
    }

    public function getCompanyOptionsProperty()
    {
        return Companies::pluck('name', 'id');
    }

    public function getCanBrowseAllCompaniesProperty(): bool
    {
        return Auth::user()->canFilterByCompany();
    }

    public function getEmployeeOptionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return User::where('company_id', $this->companyId)->orderBy('name')->pluck('name', 'id');
    }

    protected function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'coach_by' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'improvement_due_date' => ['required', 'date'],
            'goals' => ['nullable', 'string'],
            'employee_condition' => ['nullable', 'string'],
            'solution' => ['nullable', 'string'],
            'improvement_action' => ['nullable', 'string'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();
        $data['company_id'] = $this->companyId ?? Auth::user()->company_id;

        $coaching = app(CoachingService::class)->create($data);

        Notification::make()->title('Coaching berhasil dibuat')->success()->send();

        $this->redirect(route('coaching.show', $coaching), navigate: true);
    }

    public function render()
    {
        return view('livewire.coaching.create-coaching-page');
    }
}
