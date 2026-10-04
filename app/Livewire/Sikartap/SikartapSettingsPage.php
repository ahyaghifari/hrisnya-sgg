<?php

namespace App\Livewire\Sikartap;

use App\Enum\CustomPermissionEnum;
use App\Enum\OrgStructurLevel;
use App\Enum\Sikartap\SikartapSettingEnum;
use App\Models\AuditLog;
use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use App\Models\Setting;
use App\Models\Master\JobPositions;
use App\Models\SikartapDirectSupervisorPosition;
use App\Models\SikartapReviewer;
use App\Models\Sikartap\SikartapIdeInovasiReviewer;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Pengaturan Sikartap" - toggle per company: wajib Ide Inovasi & wajib MCU
 * (App\Enum\Sikartap\SikartapSettingEnum). Layout niru pola persis EmploymentSettingsPage
 * (Kepegawaian) - kiri daftar company, kanan form toggle.
 */
#[Layout('components.layout', ['title' => 'Pengaturan Sikartap'])]
class SikartapSettingsPage extends Component
{
    public ?string $companyId = null;

    /** @var array<string, string> SikartapSettingEnum value => value (company terpilih) */
    public array $values = [];

    public ?string $viewingAuditKey = null;

    /** @var array<string, string> org_structure_id (Departemen) => user_id terpilih - staged,
     * baru ditulis ke DB pas saveReviewers() (2026-08-26, instruksi user: 1 tombol Simpan di
     * bawah, bukan simpan langsung per baris). */
    public array $reviewerUserIds = [];

    /** Jabatan pendukung (1 per perusahaan) yang ikut menyetujui jadwal presentasi orientasi
     * — selain HR. Setting orientation_presentation_approver_job_position_id. */
    public ?string $presentationApproverJobPositionId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_PENGATURAN_SIKARTAP->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;

        $this->loadCompanyState();
        $this->loadReviewerSelections();
        $this->loadContractDocs();
        $this->loadPresentationApproverSetting();
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->loadReviewerSelections();
        $this->loadContractDocs();
        $this->loadPresentationApproverSetting();
    }

    public function selectCompany(string $companyId): void
    {
        abort_unless(Auth::user()->canFilterByCompany(), 403);

        $this->companyId = $companyId;
        $this->loadCompanyState();
        $this->loadReviewerSelections();
        $this->loadContractDocs();
        $this->loadPresentationApproverSetting();
    }

    private function loadPresentationApproverSetting(): void
    {
        $this->presentationApproverJobPositionId = $this->companyId
            ? Setting::resolve(\App\Services\OrientationService::PRESENTATION_APPROVER_SETTING_KEY, $this->companyId, default: null)
            : null;
    }

    public function savePresentationApprover(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_SIKARTAP->value), 403);

        $this->validate([
            'presentationApproverJobPositionId' => ['required', 'exists:m_job_positions,id'],
        ], [], ['presentationApproverJobPositionId' => 'jabatan pendukung']);

        Setting::updateOrCreate(
            ['key' => \App\Services\OrientationService::PRESENTATION_APPROVER_SETTING_KEY, 'company_id' => $this->companyId],
            [
                'module' => 'orientation',
                'type' => 'string',
                'label' => 'Jabatan Pendukung Persetujuan Jadwal Presentasi Orientasi',
                'value' => $this->presentationApproverJobPositionId,
            ],
        );

        Notification::make()->title('Jabatan pendukung tersimpan')->success()->send();
    }

    /** Pemegang aktif jabatan pendukung terpilih — konfirmasi tampilan. */
    public function getPresentationApproverHoldersProperty()
    {
        if (! $this->presentationApproverJobPositionId) {
            return collect();
        }

        return \App\Models\User\UserJobPositions::query()
            ->where('job_position_id', $this->presentationApproverJobPositionId)
            ->where('status', 'active')
            ->with('user:id,name')
            ->get()
            ->pluck('user.name')
            ->filter()
            ->values();
    }

    /** Muat pilihan reviewer SAAT INI per Departemen dari DB - dipanggil tiap kali company
     * ganti, supaya dropdown terisi nilai yang sudah tersimpan (bukan selalu kosong). */
    private function loadReviewerSelections(): void
    {
        if (! $this->companyId) {
            $this->reviewerUserIds = [];
            return;
        }

        $this->reviewerUserIds = SikartapIdeInovasiReviewer::where('company_id', $this->companyId)
            ->pluck('user_id', 'org_structure_id')
            ->all();
    }

    private function loadCompanyState(): void
    {
        if (! $this->companyId) {
            $this->values = [];
            return;
        }

        $keys = collect(SikartapSettingEnum::cases())->map(fn ($e) => $e->value);

        $companyRows = Setting::where('company_id', $this->companyId)->whereIn('key', $keys)->pluck('value', 'key');
        $globalFallback = Setting::whereNull('company_id')->whereIn('key', $keys)->pluck('value', 'key');

        $this->values = collect(SikartapSettingEnum::cases())
            ->mapWithKeys(fn ($e) => [$e->value => (string) ($companyRows[$e->value] ?? $globalFallback[$e->value] ?? $e->default())])
            ->all();
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function openAuditLog(string $key): void
    {
        $this->viewingAuditKey = $key;
    }

    public function closeAuditLog(): void
    {
        $this->viewingAuditKey = null;
    }

    public function getAuditLogsProperty()
    {
        if (! $this->viewingAuditKey) {
            return collect();
        }

        return AuditLog::where('company_id', $this->companyId)
            ->where('key', $this->viewingAuditKey)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /** @return SikartapSettingEnum[] */
    public function getSettingsProperty(): array
    {
        return SikartapSettingEnum::cases();
    }

    public function getCanEditSettingsProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_SIKARTAP->value);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_SIKARTAP->value), 403);

        if (! $this->companyId) {
            return;
        }

        foreach ($this->values as $key => $value) {
            $case = SikartapSettingEnum::from($key);

            Setting::updateOrCreate(
                ['company_id' => $this->companyId, 'key' => $key],
                [
                    'module' => 'sikartap',
                    'type' => $case->type()->value,
                    'label' => $case->label(),
                    'description' => $case->helperText(),
                    'value' => $value,
                ]
            );
        }

        $companyName = $this->companies->firstWhere('id', $this->companyId)?->name ?? 'perusahaan ini';

        Notification::make()
            ->title("Pengaturan Sikartap {$companyName} berhasil disimpan")
            ->success()
            ->send();
    }

    /** Departemen (level 1) saja - disederhanakan (2026-08-26, instruksi user), bukan
     * Departemen-atau-Divisi lagi. */
    public function getOrgDepartmentsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return OrgStructure::where('company_id', $this->companyId)
            ->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
            ->orderBy('name')
            ->get();
    }

    public function getCompanyUsersProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return User::whereCompany($this->companyId)->orderByName()->get();
    }

    /**
     * 1 reviewer per Departemen (2026-08-26, instruksi user) - tolak simpan kalau ADA Departemen
     * yang belum terisi, satu tombol Simpan di bawah untuk semua Departemen sekaligus (bukan
     * simpan langsung per baris seperti sebelumnya).
     */
    public function saveReviewers(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_SIKARTAP->value), 403);

        if (! $this->companyId) {
            return;
        }

        $departments = $this->orgDepartments;

        $kosong = $departments->reject(fn (OrgStructure $d) => filled($this->reviewerUserIds[$d->id] ?? null));

        if ($kosong->isNotEmpty()) {
            Notification::make()
                ->title('Belum bisa disimpan')
                ->body('Departemen berikut belum punya reviewer: ' . $kosong->pluck('name')->implode(', '))
                ->danger()
                ->send();

            return;
        }

        foreach ($departments as $department) {
            SikartapIdeInovasiReviewer::updateOrCreate(
                ['company_id' => $this->companyId, 'org_structure_id' => $department->id],
                ['user_id' => $this->reviewerUserIds[$department->id]]
            );
        }

        Notification::make()->title('Reviewer Ide Inovasi berhasil disimpan')->success()->send();
    }

    // ── Penilai Perjalanan Status Karyawan (sikartap_reviewers, context status_karyawan) ──
    // Satu set penilai dipakai untuk Orientasi & Perpanjangan Kontrak (digabung 2026-09-07).
    // Jabatan-based (tahan personil ganti). Independen dari toggle "wajib Ide Inovasi".

    public ?string $statusReviewerDeptId = null;
    public ?string $statusReviewerJobPositionId = null;

    public function getStatusReviewersProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return SikartapReviewer::query()
            ->forContext(SikartapReviewer::CONTEXT_STATUS_KARYAWAN)
            ->where('company_id', $this->companyId)
            ->with(['orgStructure', 'jobPosition'])
            ->get();
    }

    public function getCompanyJobPositionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return JobPositions::where('company_id', $this->companyId)->orderBy('name')->pluck('name', 'id');
    }

    /**
     * Semua jabatan LINTAS perusahaan (label dibubuhi nama perusahaan) — dipakai jabatan
     * pendukung persetujuan presentasi orientasi, yang boleh dijabat oleh posisi di perusahaan
     * lain (mis. holding) dari karyawan yang dinilai.
     */
    public function getAllJobPositionsProperty()
    {
        return JobPositions::query()
            ->with('company:id,name')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (JobPositions $jp) => [$jp->id => "{$jp->name} — {$jp->company?->name}"]);
    }

    public function addStatusReviewer(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_SIKARTAP->value), 403);

        $this->validate([
            'statusReviewerDeptId' => 'required|exists:m_org_structures,id',
            'statusReviewerJobPositionId' => 'required|exists:m_job_positions,id',
        ], [], [
            'statusReviewerDeptId' => 'departemen',
            'statusReviewerJobPositionId' => 'jabatan',
        ]);

        SikartapReviewer::firstOrCreate([
            'context' => SikartapReviewer::CONTEXT_STATUS_KARYAWAN,
            'company_id' => $this->companyId,
            'org_structure_id' => $this->statusReviewerDeptId,
            'job_position_id' => $this->statusReviewerJobPositionId,
        ]);

        $this->statusReviewerDeptId = null;
        $this->statusReviewerJobPositionId = null;

        Notification::make()->title('Penilai ditambahkan')->success()->send();
    }

    public function removeStatusReviewer(string $id): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_SIKARTAP->value), 403);

        SikartapReviewer::whereKey($id)->delete();

        Notification::make()->title('Penilai dihapus')->success()->send();
    }

    // ── Jabatan "dinilai atasan langsung" (sikartap_direct_supervisor_positions) ──
    // Jabatan yang ditandai di sini SKIP skema by-Departemen di atas — penilai langsung
    // pemegang aktif JobPositions.parent_id (2026-09-17, instruksi user: kasus RS, perawat
    // dinilai kepala ruangan masing-masing).

    public ?string $directSupervisorJobPositionId = null;

    public function getDirectSupervisorPositionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return SikartapDirectSupervisorPosition::query()
            ->forContext(SikartapReviewer::CONTEXT_STATUS_KARYAWAN)
            ->where('company_id', $this->companyId)
            ->with('jobPosition')
            ->get();
    }

    public function addDirectSupervisorPosition(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_SIKARTAP->value), 403);

        $this->validate([
            'directSupervisorJobPositionId' => 'required|exists:m_job_positions,id',
        ], [], ['directSupervisorJobPositionId' => 'jabatan']);

        SikartapDirectSupervisorPosition::firstOrCreate([
            'context' => SikartapReviewer::CONTEXT_STATUS_KARYAWAN,
            'company_id' => $this->companyId,
            'job_position_id' => $this->directSupervisorJobPositionId,
        ]);

        $this->directSupervisorJobPositionId = null;

        Notification::make()->title('Jabatan atasan-langsung ditambahkan')->success()->send();
    }

    public function removeDirectSupervisorPosition(string $id): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_SIKARTAP->value), 403);

        SikartapDirectSupervisorPosition::whereKey($id)->delete();

        Notification::make()->title('Jabatan atasan-langsung dihapus')->success()->send();
    }

    // ── Dokumen Perpanjangan Kontrak (Setting contract_extension_documents) ───────

    /** @var array<int, array{label:string, required:bool}> */
    public array $contractDocRows = [];

    private function loadContractDocs(): void
    {
        $this->contractDocRows = app(\App\Services\ContractExtensionService::class)
            ->requiredDocuments($this->companyId);
    }

    public function addContractDoc(): void
    {
        abort_unless($this->canEditSettings, 403);
        $this->contractDocRows[] = ['label' => '', 'required' => true];
    }

    public function removeContractDoc(int $index): void
    {
        abort_unless($this->canEditSettings, 403);
        unset($this->contractDocRows[$index]);
        $this->contractDocRows = array_values($this->contractDocRows);
    }

    public function moveContractDocUp(int $index): void
    {
        abort_unless($this->canEditSettings, 403);
        if ($index > 0 && isset($this->contractDocRows[$index])) {
            [$this->contractDocRows[$index - 1], $this->contractDocRows[$index]] = [$this->contractDocRows[$index], $this->contractDocRows[$index - 1]];
        }
    }

    public function moveContractDocDown(int $index): void
    {
        abort_unless($this->canEditSettings, 403);
        if ($index < count($this->contractDocRows) - 1 && isset($this->contractDocRows[$index])) {
            [$this->contractDocRows[$index + 1], $this->contractDocRows[$index]] = [$this->contractDocRows[$index], $this->contractDocRows[$index + 1]];
        }
    }

    public function saveContractDocs(): void
    {
        abort_unless($this->canEditSettings, 403);

        $this->validate([
            'contractDocRows.*.label' => 'required|string|max:255',
        ], [], [
            'contractDocRows.*.label' => 'nama dokumen',
        ]);

        $clean = collect($this->contractDocRows)
            ->map(fn ($r) => ['label' => trim($r['label']), 'required' => (bool) ($r['required'] ?? false)])
            ->filter(fn ($r) => $r['label'] !== '')
            ->values()
            ->all();

        app(\App\Services\ContractExtensionService::class)->saveRequiredDocuments($this->companyId, $clean);

        Notification::make()->title('Dokumen perpanjangan kontrak tersimpan')->success()->send();
    }

    public function render()
    {
        return view('livewire.sikartap.sikartap-settings-page');
    }
}
