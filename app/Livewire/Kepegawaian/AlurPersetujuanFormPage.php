<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\CustomPermissionEnum;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tambah/Edit Alur Persetujuan - versi portal dari admin Filament ApprovalFlowForm
 * (app/Filament/Resources/ApprovalFlows/Schemas/ApprovalFlowForm.php), ditaruh di modul
 * Kepegawaian (instruksi user 2026-08-16). Company-lock-on-edit persis pola
 * App\Livewire\Presensi\CreateSchedulePage (Super Admin pilih company pas bikin baru, terkunci
 * pas edit). "steps" array-of-rows dinamis persis pola
 * App\Livewire\Presensi\Concerns\HandlesShiftScheduleForm::$shiftTypes.
 */
#[Layout('components.layout', ['title' => 'Alur Persetujuan'])]
class AlurPersetujuanFormPage extends Component
{
    private const HOLDING_COMPANY_ID = '01J2F0C8KQ9M7D4S8RBX6E1ZVP';

    public ?string $editingFlowId = null;

    public ?string $companyId = null;

    public string $name = '';

    public string $module = '';

    public string $flowType = 'default';

    public bool $isActive = true;

    /** @var array<int, string> id JobPositions, cuma relevan flowType=specific. */
    public array $assignmentJobPositions = [];

    /** @var array<int, array<string, mixed>> baris step, shape sama kolom MasterApprovalFlowSteps
     * + 'source_company' (bukan kolom DB, cuma penentu scope query approver_ref_id options). */
    public array $steps = [];

    public function mount(?MasterApprovalFlows $flow = null): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::KELOLA_ALUR_PERSETUJUAN->value), 403);

        $this->companyId = Auth::user()->company_id;

        if (! $flow || ! $flow->exists) {
            $this->addStep();

            return;
        }

        $flow->load(['steps', 'assignments']);

        $this->editingFlowId = $flow->id;
        $this->companyId = $flow->company_id;
        $this->name = $flow->name;
        $this->module = $flow->module;
        $this->flowType = $flow->flow_type;
        $this->isActive = (bool) $flow->is_active;
        $this->assignmentJobPositions = $flow->assignments->pluck('assignable_id')->all();

        $this->steps = $flow->steps->map(fn ($step) => [
            'id' => $step->id,
            'step_order' => $step->step_order,
            'approver_type' => $step->approver_type,
            'source_company' => 'same_company',
            'approver_ref_id' => $step->approver_ref_id,
            'scope_type' => $step->scope_type,
            'scope_ref_id' => $step->scope_ref_id,
            'is_final' => (bool) $step->is_final,
        ])->all();

        if (empty($this->steps)) {
            $this->addStep();
        }
    }

    /** Ganti perusahaan -> semua pilihan approver/departemen di steps jadi tidak relevan lagi
     * (beda company), sama alasan CreateSchedulePage::updatedCompanyId() reset karyawan
     * terpilih. */
    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        foreach ($this->steps as &$step) {
            $step['approver_ref_id'] = '';
            $step['scope_ref_id'] = null;
        }
    }

    public function getModuleOptionsProperty(): array
    {
        return ApprovalModuleEnum::options();
    }

    public function getCompaniesProperty()
    {
        return Auth::user()->canFilterByCompany() ? Companies::orderBy('name')->get(['id', 'name']) : collect();
    }

    public function getJobPositionOptionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return JobPositions::where('company_id', $this->companyId)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function addStep(): void
    {
        $this->steps[] = [
            'id' => null,
            'step_order' => count($this->steps) + 1,
            'approver_type' => '',
            'source_company' => 'same_company',
            'approver_ref_id' => '',
            'scope_type' => 'same_department',
            'scope_ref_id' => null,
            'is_final' => false,
        ];
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);

        foreach ($this->steps as $i => &$step) {
            $step['step_order'] = $i + 1;
        }
    }

    /** Opsi dropdown "Approver" per baris step - dipanggil langsung dari blade (bukan computed
     * property, parameternya beda tiap baris). $sourceCompany 'holding_company' = cari di
     * company holding, 'same_company' = cari di $this->companyId (company yang lagi diedit,
     * BUKAN Auth::user()->company_id - itu bug yang sama yang diperbaiki di admin Filament
     * 2026-08-16, jangan diulang di sini). */
    public function approverOptions(string $approverType, string $sourceCompany): array
    {
        if (! $approverType) {
            return [];
        }

        $companyId = $sourceCompany === 'holding_company' ? self::HOLDING_COMPANY_ID : $this->companyId;

        return match ($approverType) {
            'role' => Role::where('company_id', $companyId)->orderBy('name')->pluck('name', 'id')->all(),
            'user' => User::where('company_id', $companyId)->orderBy('name')->pluck('name', 'id')->all(),
            'job_position' => JobPositions::where('company_id', $companyId)->orderBy('name')->pluck('name', 'id')->all(),
            default => [],
        };
    }

    public function getScopeDepartmentOptionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return OrgStructure::where('company_id', $this->companyId)->orderBy('name')->pluck('name', 'id');
    }

    public function save(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        // Mode Edit: perusahaan terkunci total - sama alasan & pola CreateSchedulePage::save()
        // (2026-08-16), alur yang sudah dipakai (snapshot approval, dst) terikat ke company_id
        // asalnya.
        if ($this->editingFlowId) {
            $this->companyId = MasterApprovalFlows::findOrFail($this->editingFlowId)->company_id;
        }

        $this->validate([
            'companyId' => ['required'],
            'name' => ['required', 'string', 'max:255'],
            'module' => ['required', 'in:' . implode(',', array_column(ApprovalModuleEnum::cases(), 'value'))],
            'flowType' => ['required', 'in:default,specific'],
            'assignmentJobPositions' => $this->flowType === 'specific' ? ['required', 'array', 'min:1'] : ['array'],
        ], [], [
            'companyId' => 'perusahaan',
            'name' => 'nama alur',
            'module' => 'modul',
            'flowType' => 'tipe alur',
            'assignmentJobPositions' => 'jabatan berlaku',
        ]);

        // Cuma 1 alur Default per modul per company (sama aturan admin Filament).
        if ($this->flowType === 'default') {
            $exists = MasterApprovalFlows::where('company_id', $this->companyId)
                ->where('module', $this->module)
                ->where('flow_type', 'default')
                ->when($this->editingFlowId, fn ($q) => $q->where('id', '!=', $this->editingFlowId))
                ->exists();

            if ($exists) {
                $this->addError('module', 'Alur Persetujuan Default untuk Modul ini sudah ada. Edit yang sudah ada, atau gunakan tipe Spesifik.');

                return;
            }
        }

        DB::transaction(function () {
            $data = [
                'company_id' => $this->companyId,
                'name' => $this->name,
                'module' => $this->module,
                'flow_type' => $this->flowType,
                'is_active' => $this->isActive,
                'updated_at' => now(),
                'updated_by' => Auth::id(),
            ];

            $flow = $this->editingFlowId
                ? tap(MasterApprovalFlows::findOrFail($this->editingFlowId))->update($data)
                : MasterApprovalFlows::create($data);

            $flow->assignments()->delete();
            if ($this->flowType === 'specific') {
                foreach ($this->assignmentJobPositions as $jobPositionId) {
                    $flow->assignments()->create([
                        'assignable_id' => $jobPositionId,
                        'assignable_type' => JobPositions::class,
                    ]);
                }
            }

            $flow->steps()->delete();
            foreach ($this->steps as $index => $step) {
                if (empty($step['approver_ref_id']) || empty($step['approver_type'])) {
                    continue;
                }

                $flow->steps()->create([
                    'step_order' => $index + 1,
                    'approver_type' => $step['approver_type'],
                    'approver_ref_id' => $step['approver_ref_id'],
                    'approval_mode' => $step['approver_type'] === 'job_position' ? 'all' : 'any',
                    'scope_type' => $step['scope_type'] ?: 'same_department',
                    'scope_ref_id' => $step['scope_type'] === 'specific_department' ? $step['scope_ref_id'] : null,
                    'is_final' => (bool) $step['is_final'],
                ]);
            }

            $this->editingFlowId = $flow->id;
        });

        Notification::make()
            ->title($this->editingFlowId ? 'Alur persetujuan berhasil disimpan' : 'Alur persetujuan berhasil dibuat')
            ->success()
            ->send();

        $this->redirect(route('kepegawaian.alur-persetujuan'), navigate: true);
    }

    public function render()
    {
        return view('livewire.kepegawaian.alur-persetujuan-form-page');
    }
}
