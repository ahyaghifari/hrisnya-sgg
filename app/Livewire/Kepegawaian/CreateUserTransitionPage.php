<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\OrgStructurLevel;
use App\Enum\UserTransitionTypeEnum;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\Role;
use App\Models\User;
use App\Services\User\UserTransitionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Form "Ajukan Perubahan Status Karyawan" (Promosi/Demosi/Mutasi/PHK) versi portal — replikasi
 * form admin (UserTransitionForm) supaya HC/atasan bisa ajukan tanpa masuk admin panel. Logic
 * simpan + snapshot approval reuse UserTransitionService (dipakai bareng admin), bukan
 * diduplikasi di sini.
 */
#[Layout('components.layout', ['title' => 'Ajukan Perubahan Status Karyawan'])]
class CreateUserTransitionPage extends Component
{
    use WithFileUploads;

    public ?string $companyId = null;
    public ?string $user_id = null;
    public string $transition_type = '';

    // Promosi/Demosi
    public ?string $transitionable_id = null;
    public ?string $effective_date = null;
    public ?string $role_id = null;

    // Demosi
    public ?string $reason_option = null;

    // Termination & "alasan lainnya" demosi
    public ?string $reason = null;

    public ?string $manager_comment = null;

    // Evaluasi kinerja (Promosi/Demosi)
    public ?string $target_achievement = null;
    public ?string $target_comment = null;
    public ?string $work_quality = null;
    public ?string $work_quality_comment = null;
    public ?string $productivity_level = null;
    public ?string $productivity_comment = null;
    public ?string $responsibility_adherence = null;
    public ?string $responsibility_comment = null;
    public ?string $work_ethic = null;
    public ?string $work_ethic_comment = null;
    public ?string $teamwork = null;
    public ?string $teamwork_comment = null;
    public ?string $external_factors = null;
    public ?string $employee_request = null;
    public ?string $other_comments = null;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can('Create:UserTransition'), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        // Pre-select jenis transisi sesuai tombol "Ajukan X" yang diklik dari list
        // (kepegawaian.mutasi-karyawan), bukan berarti tidak bisa diganti lagi di form.
        $type = request()->query('type');
        if ($type && UserTransitionTypeEnum::tryFrom($type)) {
            $this->transition_type = $type;
        }
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
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

    public function getEmployeesProperty()
    {
        return $this->companyId
            ? User::where('company_id', $this->companyId)->orderBy('name')->get()
            : collect();
    }

    private function getTargetUser(): ?User
    {
        return $this->user_id ? User::find($this->user_id) : null;
    }

    public function getJobPositionsProperty()
    {
        $target = $this->getTargetUser();

        return $target ? JobPositions::where('company_id', $target->company_id)->pluck('name', 'id') : collect();
    }

    public function getDepartmentsProperty()
    {
        $target = $this->getTargetUser();

        return $target
            ? OrgStructure::where('company_id', $target->company_id)->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)->pluck('name', 'id')
            : collect();
    }

    public function getRolesProperty()
    {
        $target = $this->getTargetUser();

        return $target ? Role::where('company_id', $target->company_id)->pluck('name', 'id') : collect();
    }

    public function updatedUserId(): void
    {
        $this->transitionable_id = null;
        $this->role_id = null;
    }

    public function updatedTransitionableId(?string $value): void
    {
        if (in_array($this->transition_type, [UserTransitionTypeEnum::PROMOTION->value, UserTransitionTypeEnum::DEMOTION->value], true)) {
            $this->role_id = $value ? JobPositions::find($value)?->role_id : null;
        }
    }

    protected function rules(): array
    {
        $rules = [
            'user_id' => ['required', 'exists:users,id'],
            'transition_type' => ['required', 'in:' . implode(',', array_column(UserTransitionTypeEnum::cases(), 'value'))],
        ];

        if (in_array($this->transition_type, [UserTransitionTypeEnum::PROMOTION->value, UserTransitionTypeEnum::DEMOTION->value], true)) {
            $rules['transitionable_id'] = ['required', 'exists:m_job_positions,id'];
            $rules['effective_date'] = ['required', 'date'];
        }

        if ($this->transition_type === UserTransitionTypeEnum::DEMOTION->value) {
            $rules['reason_option'] = ['required', 'string'];
        }

        if ($this->transition_type === UserTransitionTypeEnum::TERMINATION->value) {
            $rules['reason'] = ['required', 'string'];
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        return [
            'user_id' => 'karyawan',
            'transition_type' => 'jenis perubahan',
            'transitionable_id' => 'jabatan/departemen baru',
            'effective_date' => 'tanggal efektif',
            'reason_option' => 'alasan',
            'reason' => 'alasan',
        ];
    }

    public function submit(): void
    {
        $this->validate();

        $data = [
            'user_id' => $this->user_id,
            'transition_type' => $this->transition_type,
            'transitionable_id' => $this->transitionable_id,
            'role_id' => $this->role_id,
            'effective_date' => $this->effective_date,
            'reason' => $this->reason_option === 'other' || $this->transition_type === UserTransitionTypeEnum::TERMINATION->value
                ? $this->reason
                : $this->reason_option,
            'manager_comment' => $this->manager_comment,
        ];

        $detailData = [];

        if (in_array($this->transition_type, [UserTransitionTypeEnum::PROMOTION->value, UserTransitionTypeEnum::DEMOTION->value], true)) {
            $detailData = [
                'target_achievement' => $this->target_achievement,
                'target_comment' => $this->target_comment,
                'work_quality' => $this->work_quality,
                'work_quality_comment' => $this->work_quality_comment,
                'productivity_level' => $this->productivity_level,
                'productivity_comment' => $this->productivity_comment,
                'responsibility_adherence' => $this->responsibility_adherence,
                'responsibility_comment' => $this->responsibility_comment,
                'work_ethic' => $this->work_ethic,
                'work_ethic_comment' => $this->work_ethic_comment,
                'teamwork' => $this->teamwork,
                'teamwork_comment' => $this->teamwork_comment,
                'external_factors' => $this->external_factors,
                'employee_request' => $this->employee_request,
                'other_comments' => $this->other_comments,
            ];
        }

        $attachmentPaths = collect($this->attachments)
            ->map(fn ($file) => $file->store('user-transitions', 'public'))
            ->all();

        try {
            $transition = app(UserTransitionService::class)->create($data, $detailData, $attachmentPaths, Auth::user());

            Notification::make()
                ->title('Pengajuan berhasil dibuat')
                ->success()
                ->send();

            $this->redirect(route('kepegawaian.mutasi-karyawan.show', $transition), navigate: true);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal membuat pengajuan')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.kepegawaian.create-user-transition-page');
    }
}
