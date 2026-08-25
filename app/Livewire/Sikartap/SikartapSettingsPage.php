<?php

namespace App\Livewire\Sikartap;

use App\Enum\CustomPermissionEnum;
use App\Enum\OrgStructurLevel;
use App\Enum\Sikartap\SikartapSettingEnum;
use App\Models\AuditLog;
use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use App\Models\Setting;
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

    // Penunjukan manual reviewer atasan Ide Inovasi per node Divisi (level 2) org struktur -
    // Kabag/Kabid itu jabatan per divisi, lihat Sikartap::ideInovasiOrgDivision().
    public string $newReviewerOrgId = '';

    public string $newReviewerUserId = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_PENGATURAN_SIKARTAP->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;

        $this->loadCompanyState();
    }

    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function selectCompany(string $companyId): void
    {
        abort_unless(Auth::user()->canFilterByCompany(), 403);

        $this->companyId = $companyId;
        $this->loadCompanyState();
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

    public function getOrgDivisionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return OrgStructure::where('company_id', $this->companyId)
            ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
            ->with('parent')
            ->orderBy('name')
            ->get();
    }

    public function getReviewerAssignmentsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return SikartapIdeInovasiReviewer::where('company_id', $this->companyId)
            ->with('user')
            ->get()
            ->groupBy('org_structure_id');
    }

    public function getCompanyUsersProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return User::where('company_id', $this->companyId)->orderBy('name')->get(['id', 'name']);
    }

    public function addReviewer(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_SIKARTAP->value), 403);

        $this->validate([
            'newReviewerOrgId' => ['required', 'exists:m_org_structures,id'],
            'newReviewerUserId' => ['required', 'exists:users,id'],
        ]);

        SikartapIdeInovasiReviewer::firstOrCreate([
            'company_id' => $this->companyId,
            'org_structure_id' => $this->newReviewerOrgId,
            'user_id' => $this->newReviewerUserId,
        ]);

        $this->newReviewerOrgId = '';
        $this->newReviewerUserId = '';

        Notification::make()->title('Reviewer ditambahkan')->success()->send();
    }

    public function removeReviewer(string $id): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_SIKARTAP->value), 403);

        SikartapIdeInovasiReviewer::where('company_id', $this->companyId)->where('id', $id)->delete();

        Notification::make()->title('Reviewer dihapus')->success()->send();
    }

    public function render()
    {
        return view('livewire.sikartap.sikartap-settings-page');
    }
}
