<?php

namespace App\Livewire\Rekrutmen;

use App\Enum\Recruitment\ProcessStatus;
use App\Enum\Recruitment\ProcessType;
use App\Enum\Recruitment\RecruitmentProcessStatus;
use App\Models\Candidate\CandidateJobApplications;
use App\Models\Candidate\CandidateRecruitmentProcess;
use App\Models\Master\EmployeeTypes;
use App\Models\Role;
use App\Services\Candidate\CandidateApplicationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Detail kandidat + seluruh alur proses rekrutmen — porting penuh dari
 * `App\Filament\Resources\ManPower\JobOpenings\Pages\ViewCandidateApplicant`. Semua efek domain
 * didelegasikan ke CandidateApplicationService supaya identik dengan admin (kalau admin masih
 * dipakai bersamaan).
 */
#[Layout('components.layout', ['title' => 'Detail Kandidat'])]
class CandidateApplicationDetailPage extends Component
{
    use WithFileUploads;

    public CandidateJobApplications $applicant;
    public string $activeTab = 'detail';

    // Approval Direktur
    public string $directorNotes = '';
    public bool $showApproveCandidateForm = false;
    public bool $showRejectCandidateForm = false;

    // Lanjutkan Tahapan Rekrutmen
    public bool $showAddProcessForm = false;
    public string $process_type = '';
    public string $process_name = '';
    public ?string $scheduled_at = null;
    public ?string $maximum_completion_at = null;
    public string $link = '';
    public string $notes = '';
    public array $attachments = [];

    // Footer: request approval
    public bool $showRequestApprovalForm = false;
    public string $requestApprovalNotes = '';

    // Footer: terima kandidat
    public bool $showAcceptForm = false;
    public string $nip = '';
    public string $type_nakes = '';
    public ?string $employee_type_id = null;
    public ?string $joined_at = null;
    public ?string $role_id = null;

    // Per-proses: skor
    public ?string $scoringProcessId = null;
    public string $scoreStatus = '';
    public ?string $scoreValue = null;
    public string $scoreFeedback = '';

    // Per-proses: MCU
    public ?string $mcuProcessId = null;
    public string $mcuResultsSummary = '';
    public $mcuAttachment = null;

    public function mount(CandidateJobApplications $record): void
    {
        abort_unless(Auth::user()->can('View:JobOpenings'), 403);

        $record->load([
            'candidate.familyMembers',
            'candidate.educations',
            'candidate.trainings',
            'candidate.organizations',
            'candidate.workExperiences',
            'candidate.references',
            'candidate.additionalInfos',
            'candidate.selfAssessments',
            'candidate.documents',
            'jobVacancy.jobPosition.orgStructure',
            'jobVacancy.company',
            'approver',
            'recruitmentProcesses' => fn ($q) => $q->orderByDesc('id'),
        ]);

        abort_unless(
            Auth::user()->canFilterByCompany() || $record->jobVacancy?->company_id === Auth::user()->company_id,
            403
        );

        $this->applicant = $record;
        $this->joined_at = now()->toDateString();
    }

    public function getProcessTypesProperty(): array
    {
        return ProcessType::cases();
    }

    public function getEmployeeTypeOptionsProperty()
    {
        return EmployeeTypes::pluck('name', 'id');
    }

    public function getRoleOptionsProperty()
    {
        return Role::where('company_id', $this->applicant->jobVacancy->company_id)->pluck('name', 'id');
    }

    // --- Visibility gates (mirror ViewCandidateApplicant) ---

    public function getCanApproveOrRejectCandidateProperty(): bool
    {
        return $this->applicant->approval_status === 'requested';
    }

    public function getCanProcessRecruitmentProperty(): bool
    {
        return $this->applicant->status === ProcessStatus::SUBMITTED->value;
    }

    public function getCanAddRecruitmentProcessProperty(): bool
    {
        return ! in_array($this->applicant->status, [
            ProcessStatus::HIRED->value,
            ProcessStatus::REJECTED->value,
            ProcessStatus::WITHDRAWN->value,
            ProcessStatus::SUBMITTED->value,
        ], true);
    }

    public function getCanRequestApprovalProperty(): bool
    {
        return $this->applicant->approval_status !== 'requested' && $this->applicant->approval_status !== 'approved';
    }

    public function getCanRejectApplicationProperty(): bool
    {
        return $this->applicant->status !== ProcessStatus::REJECTED->value && $this->applicant->status !== ProcessStatus::HIRED->value;
    }

    public function getCanAcceptApplicationProperty(): bool
    {
        return $this->applicant->status !== ProcessStatus::HIRED->value && $this->applicant->status !== ProcessStatus::REJECTED->value;
    }

    public function canAddScore(CandidateRecruitmentProcess $process): bool
    {
        return ! in_array($process->status, [
            RecruitmentProcessStatus::COMPLETED->value,
            RecruitmentProcessStatus::ACCEPTED->value,
            RecruitmentProcessStatus::FAILED->value,
        ], true);
    }

    public function canConfirmReschedule(CandidateRecruitmentProcess $process): bool
    {
        return $process->status === RecruitmentProcessStatus::REQUEST_RESCHEDULED->value;
    }

    public function canMarkMcuCompleted(CandidateRecruitmentProcess $process): bool
    {
        return $process->process_type === ProcessType::MCU->value
            && in_array($process->status, [RecruitmentProcessStatus::ACCEPTED->value, RecruitmentProcessStatus::RESCHEDULED->value], true);
    }

    public function canDeleteProcess(CandidateRecruitmentProcess $process): bool
    {
        return $process->status === RecruitmentProcessStatus::ASSIGNED->value;
    }

    private function refreshApplicant(): void
    {
        $this->applicant->refresh()->load(['recruitmentProcesses' => fn ($q) => $q->orderByDesc('id')]);
    }

    // --- Approval Direktur ---

    public function approveCandidate(): void
    {
        abort_unless($this->canApproveOrRejectCandidate, 403);

        app(CandidateApplicationService::class)->approveCandidate($this->applicant, $this->directorNotes ?: null, Auth::id());

        $this->showApproveCandidateForm = false;
        $this->directorNotes = '';
        $this->refreshApplicant();

        Notification::make()->title('Kandidat telah disetujui.')->success()->send();
    }

    public function rejectCandidate(): void
    {
        abort_unless($this->canApproveOrRejectCandidate, 403);

        app(CandidateApplicationService::class)->rejectCandidate($this->applicant, $this->directorNotes ?: null, Auth::id());

        $this->showRejectCandidateForm = false;
        $this->directorNotes = '';
        $this->refreshApplicant();

        Notification::make()->title('Kandidat telah ditolak.')->success()->send();
    }

    // --- Status Rekrutmen ---

    public function toggleAddProcessForm(): void
    {
        $this->showAddProcessForm = ! $this->showAddProcessForm;
    }

    public function toggleRequestApprovalForm(): void
    {
        $this->showRequestApprovalForm = ! $this->showRequestApprovalForm;
    }

    public function toggleAcceptForm(): void
    {
        $this->showAcceptForm = ! $this->showAcceptForm;
    }

    public function processRecruitment(): void
    {
        abort_unless($this->canProcessRecruitment, 403);

        app(CandidateApplicationService::class)->processRecruitment($this->applicant);

        $this->refreshApplicant();

        Notification::make()->title('Lamaran sedang diproses.')->success()->send();
    }

    protected function addProcessRules(): array
    {
        return [
            'process_type' => ['required', 'string'],
            'process_name' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'maximum_completion_at' => ['nullable', 'date'],
            'link' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'attachments.*' => ['nullable', 'file', 'max:10240'],
        ];
    }

    public function submitAddProcess(): void
    {
        abort_unless($this->canAddRecruitmentProcess, 403);

        $this->validate($this->addProcessRules());

        $attachmentPaths = collect($this->attachments)
            ->map(fn ($file) => $file->store('recruitment-process-attachments', 'public'))
            ->values()
            ->all();

        app(CandidateApplicationService::class)->addRecruitmentProcess($this->applicant, [
            'process_type' => $this->process_type,
            'process_name' => $this->process_name,
            'link' => $this->link ?: null,
            'notes' => $this->notes ?: null,
            'metadata' => array_filter([
                'scheduled_at' => $this->scheduled_at,
                'maximum_completion_at' => $this->maximum_completion_at,
            ]),
        ], $attachmentPaths);

        $this->reset(['showAddProcessForm', 'process_type', 'process_name', 'scheduled_at', 'maximum_completion_at', 'link', 'notes', 'attachments']);
        $this->refreshApplicant();

        Notification::make()->title('Tahapan rekrutmen baru telah ditambahkan.')->success()->send();
    }

    // --- Per-proses ---

    public function toggleScoreForm(string $processId): void
    {
        $this->scoringProcessId = $this->scoringProcessId === $processId ? null : $processId;
        $this->scoreStatus = '';
        $this->scoreValue = null;
        $this->scoreFeedback = '';
    }

    public function submitScore(): void
    {
        $this->validate([
            'scoreStatus' => ['required', 'in:' . RecruitmentProcessStatus::COMPLETED->value . ',' . RecruitmentProcessStatus::FAILED->value],
            'scoreValue' => ['nullable', 'numeric'],
            'scoreFeedback' => ['nullable', 'string'],
        ]);

        $process = CandidateRecruitmentProcess::findOrFail($this->scoringProcessId);
        abort_unless($this->canAddScore($process), 403);

        app(CandidateApplicationService::class)->addScore($process, [
            'status' => $this->scoreStatus,
            'score' => $this->scoreValue,
            'feedback' => $this->scoreFeedback ?: null,
        ]);

        $this->scoringProcessId = null;
        $this->refreshApplicant();

        Notification::make()->title('Penilaian berhasil ditambahkan.')->success()->send();
    }

    public function confirmReschedule(string $processId): void
    {
        $process = CandidateRecruitmentProcess::findOrFail($processId);
        abort_unless($this->canConfirmReschedule($process), 403);

        app(CandidateApplicationService::class)->confirmReschedule($process);

        $this->refreshApplicant();

        Notification::make()->title('Penjadwalan ulang telah dikonfirmasi.')->success()->send();
    }

    public function toggleMcuForm(string $processId): void
    {
        $this->mcuProcessId = $this->mcuProcessId === $processId ? null : $processId;
        $this->mcuResultsSummary = '';
        $this->mcuAttachment = null;
    }

    public function submitMcu(): void
    {
        $this->validate([
            'mcuResultsSummary' => ['required', 'string'],
            'mcuAttachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $process = CandidateRecruitmentProcess::findOrFail($this->mcuProcessId);
        abort_unless($this->canMarkMcuCompleted($process), 403);

        $attachmentPath = $this->mcuAttachment
            ? $this->mcuAttachment->store('recruitment-process-attachments/result-mcu', 'public')
            : null;

        app(CandidateApplicationService::class)->markMcuCompleted($process, [
            'results_summary' => $this->mcuResultsSummary,
            'attachments' => $attachmentPath,
        ]);

        $this->mcuProcessId = null;
        $this->refreshApplicant();

        Notification::make()->title('Proses MCU telah ditandai sebagai selesai.')->success()->send();
    }

    public function deleteProcess(string $processId): void
    {
        $process = CandidateRecruitmentProcess::findOrFail($processId);
        abort_unless($this->canDeleteProcess($process), 403);

        app(CandidateApplicationService::class)->deleteProcess($process);

        $this->refreshApplicant();

        Notification::make()->title('Tahapan rekrutmen dihapus.')->success()->send();
    }

    // --- Footer actions ---

    public function submitRequestApproval(): void
    {
        abort_unless($this->canRequestApproval, 403);

        app(CandidateApplicationService::class)->requestApproval($this->applicant, $this->requestApprovalNotes ?: null);

        $this->showRequestApprovalForm = false;
        $this->requestApprovalNotes = '';
        $this->refreshApplicant();

        Notification::make()->title('Request approval kandidat telah dikirim ke direktur.')->success()->send();
    }

    public function rejectApplication(): void
    {
        abort_unless($this->canRejectApplication, 403);

        app(CandidateApplicationService::class)->rejectApplication($this->applicant);

        $this->refreshApplicant();

        Notification::make()->title('Kandidat telah ditolak.')->success()->send();
    }

    protected function acceptRules(): array
    {
        return [
            'nip' => ['required', 'string', 'max:255'],
            'type_nakes' => ['required', 'in:nakes,non_nakes'],
            'employee_type_id' => ['required', 'exists:m_employee_types,id'],
            'joined_at' => ['required', 'date'],
            'role_id' => ['required', 'exists:roles,id'],
        ];
    }

    public function submitAccept(): void
    {
        abort_unless($this->canAcceptApplication, 403);

        $data = $this->validate($this->acceptRules());

        app(CandidateApplicationService::class)->acceptApplication($this->applicant, $data);

        $this->showAcceptForm = false;
        $this->refreshApplicant();

        Notification::make()->title('Kandidat telah diterima dan data telah disinkronkan ke sistem karyawan.')->success()->send();
    }

    public function render()
    {
        return view('livewire.rekrutmen.candidate-application-detail-page');
    }
}
