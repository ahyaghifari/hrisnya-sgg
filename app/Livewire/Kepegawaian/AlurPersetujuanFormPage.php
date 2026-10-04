<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\Approval\AttendanceApprovalBucketEnum;
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

    /** @var array<int, string> ember AttendanceApprovalBucketEnum, cuma untuk module attendance_request.
     * [] = alur berlaku semua jenis (kolom sub_module NULL). */
    public array $subModule = [];

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

        // Duplikat: ?replicate={id} mengisi form dengan salinan alur sumber (belum tersimpan, jadi
        // cek bentrok di save() tetap jalan). Company-scope: non-holding cuma boleh nyalin
        // alur company sendiri. Sama pola admin Filament CreateApprovalFlow::fillForm().
        $isReplicate = false;
        if (! $flow || ! $flow->exists) {
            $flow = ($id = request()->query('replicate'))
                ? MasterApprovalFlows::query()
                    ->when(! Auth::user()->canFilterByCompany(), fn ($q) => $q->where('company_id', Auth::user()->company_id))
                    ->find($id)
                : null;

            if (! $flow) {
                $this->addStep();

                return;
            }

            $isReplicate = true;
        }

        $flow->load(['steps', 'assignments']);

        $this->editingFlowId = $isReplicate ? null : $flow->id;
        $this->companyId = $flow->company_id;
        $this->name = $isReplicate ? $flow->name . ' (Salinan)' : $flow->name;
        $this->module = $flow->module;
        $this->subModule = filled($flow->sub_module) ? explode(',', $flow->sub_module) : [];
        $this->flowType = $flow->flow_type;
        $this->isActive = (bool) $flow->is_active;
        $this->assignmentJobPositions = $flow->assignments->pluck('assignable_id')->all();

        $this->steps = $flow->steps->map(fn ($step) => [
            'id' => $isReplicate ? null : $step->id,
            'step_order' => $step->step_order,
            'approver_type' => $step->approver_type,
            'source_company' => 'same_company',
            'approver_ref_id' => $step->approver_ref_id,
            'supervisor_level' => (int) ($step->supervisor_level ?? 1),
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
        $options = ApprovalModuleEnum::options();

        // Task punya alur review sendiri (reviewer_id/needs_review di TaskService, bukan lewat
        // Approval Engine ini) - modulnya dihapus dari pilihan alur BARU, cuma tetap tampil
        // kalau alur yang lagi diedit kebetulan sudah terlanjur pakai modul ini (jangan sampai
        // dropdown-nya nge-blank pas dibuka). Sama pola dengan ApprovalFlowForm.php (admin).
        if ($this->module !== ApprovalModuleEnum::TASK_REQUEST->value) {
            unset($options[ApprovalModuleEnum::TASK_REQUEST->value]);
        }

        return $options;
    }

    public function getSubModuleOptionsProperty(): array
    {
        return AttendanceApprovalBucketEnum::options();
    }

    public function getShowSubModuleProperty(): bool
    {
        return $this->module === ApprovalModuleEnum::ATTENDANCE_REQUEST->value;
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
            'supervisor_level' => 1,
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
            'user' => User::whereCompany($companyId)->orderByName()->get()->pluck('name', 'id')->all(),
            'job_position' => JobPositions::where('company_id', $companyId)->orderBy('name')->pluck('name', 'id')->all(),
            default => [],
        };
    }

    /**
     * Rekap urutan approver sebagai 1 rantai per jabatan pengaju: base → step1 → step2 → ...
     * Step 'supervisor' = jabatan hasil naik N level dari jabatan pengaju (bukan dari step
     * sebelumnya). Step lain = nama approver tetap.
     *
     * @return array<int, array{base: string, nodes: array<int, array{label: string, ok: bool}>}>
     */
    public function approverFlowPreview(): array
    {
        $validSteps = collect($this->steps)
            ->filter(fn ($s) => ! empty($s['approver_type'])
                && ($s['approver_type'] === 'supervisor' || ! empty($s['approver_ref_id'])))
            ->values();

        $out = [];

        foreach ($this->assignmentJobPositions as $jobPositionId) {
            $base = JobPositions::find($jobPositionId);

            if (! $base) {
                continue;
            }

            $nodes = [];

            foreach ($validSteps as $s) {
                if ($s['approver_type'] === 'supervisor') {
                    $level = max(1, (int) ($s['supervisor_level'] ?? 1));
                    $pos = $base;
                    $ok = true;

                    for ($i = 0; $i < $level; $i++) {
                        $pos = $pos ? JobPositions::find($pos->parent_id) : null;

                        if (! $pos) {
                            $ok = false;
                            break;
                        }
                    }

                    $nodes[] = ['label' => $ok ? $pos->name : "(tidak ada atasan level {$level})", 'ok' => $ok];
                } else {
                    $nodes[] = ['label' => $this->stepApproverLabel($s), 'ok' => true];
                }
            }

            $out[] = ['base' => $base->name, 'nodes' => $nodes];
        }

        return $out;
    }

    /**
     * Nama approver tetap untuk step non-supervisor (jabatan/role/karyawan).
     */
    public function stepApproverLabel(array $step): string
    {
        $type = $step['approver_type'] ?? '';
        $refId = $step['approver_ref_id'] ?? null;

        if (! $refId) {
            return '(belum dipilih)';
        }

        return match ($type) {
            'job_position' => JobPositions::find($refId)?->name ?? '(jabatan tidak ada)',
            'user' => User::find($refId)?->name ?? '(karyawan tidak ada)',
            'role' => Role::find($refId)?->name ?? '(role tidak ada)',
            default => '-',
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

        // Ember jenis pengajuan (cuma modul attendance_request). [] = "semua jenis" (sub_module NULL).
        $selectedBuckets = $this->showSubModule
            ? collect($this->subModule)->filter()->values()->all()
            : [];
        $subModuleStr = $selectedBuckets ? implode(',', $selectedBuckets) : null;

        // Tolak kalau ada alur lain (company + module + flow_type sama) yang mengklaim salah satu
        // ember yang sama — atau dua-duanya "semua jenis". Untuk Spesifik cuma bentrok kalau
        // jabatannya juga beririsan. Sama aturan admin Filament (overlap-aware, bukan 1-default-saja).
        $others = MasterApprovalFlows::where('company_id', $this->companyId)
            ->where('module', $this->module)
            ->where('flow_type', $this->flowType)
            ->when($this->editingFlowId, fn ($q) => $q->where('id', '!=', $this->editingFlowId))
            ->when($this->flowType === 'specific', fn ($q) => $q->whereHas('assignments', function ($a) {
                $a->where('assignable_type', JobPositions::class)
                  ->whereIn('assignable_id', $this->assignmentJobPositions);
            }))
            ->get(['id', 'sub_module']);

        $clash = $others->contains(function ($f) use ($selectedBuckets) {
            $theirs = filled($f->sub_module) ? explode(',', $f->sub_module) : [];
            if ($selectedBuckets === [] || $theirs === []) {
                return $selectedBuckets === [] && $theirs === [];
            }

            return array_intersect($selectedBuckets, $theirs) !== [];
        });

        if ($clash) {
            $this->addError('module', $this->flowType === 'default'
                ? 'Alur Default untuk modul & jenis pengajuan ini sudah ada. Pisahkan jenis pengajuannya atau edit alur yang ada.'
                : 'Alur Spesifik untuk jabatan & jenis pengajuan ini sudah ada. Pilih jabatan lain atau pisahkan jenis pengajuannya.');

            return;
        }

        DB::transaction(function () use ($subModuleStr) {
            $data = [
                'company_id' => $this->companyId,
                'name' => $this->name,
                'module' => $this->module,
                'sub_module' => $subModuleStr,
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
                // Step 'supervisor' sah tanpa approver_ref_id — di-resolve saat snapshot.
                $isSupervisor = ($step['approver_type'] ?? null) === 'supervisor';
                if (empty($step['approver_type']) || (empty($step['approver_ref_id']) && ! $isSupervisor)) {
                    continue;
                }

                $flow->steps()->create([
                    'step_order' => $index + 1,
                    'approver_type' => $step['approver_type'],
                    'approver_ref_id' => $isSupervisor ? null : $step['approver_ref_id'],
                    'supervisor_level' => $isSupervisor ? max(1, (int) ($step['supervisor_level'] ?? 1)) : null,
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
