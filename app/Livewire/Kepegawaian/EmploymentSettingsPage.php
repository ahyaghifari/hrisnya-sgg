<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Enum\SettingTypeEnum;
use AppModelsAuditLog;
use App\Models\Master\Companies;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Pengaturan Kepegawaian" - form per company untuk KepegawaianSettingEnum (max masa
 * kerja kontrak, durasi per perpanjangan, usia pensiun). Dipakai otomatis oleh
 * UserTransitionService buat hitung contract_end_date & guard usia pensiun. Layout niru pola
 * AttendanceSettingsPage (kiri daftar company, kanan form).
 */
#[Layout('components.layout', ['title' => 'Pengaturan Kepegawaian'])]
class EmploymentSettingsPage extends Component
{
    public ?string $companyId = null;

    /** @var array<string, string> KepegawaianSettingEnum value => value (company terpilih) */
    public array $values = [];

    public ?string $viewingAuditKey = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_PENGATURAN_KEPEGAWAIAN->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;

        $this->loadCompanyState();
    }

    // Gerbang di selectCompany() saja tidak cukup - Livewire property bisa di-$set langsung
    // dari request yang di-craft (lewat hook updated*, bukan lewat method public), jadi
    // companyId perlu divalidasi ulang di sini juga.
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

        $keys = collect(KepegawaianSettingEnum::cases())->map(fn ($e) => $e->value);

        $companyRows = Setting::where('company_id', $this->companyId)->whereIn('key', $keys)->pluck('value', 'key');
        $globalFallback = Setting::whereNull('company_id')->whereIn('key', $keys)->pluck('value', 'key');

        $this->values = collect(KepegawaianSettingEnum::cases())
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

    /** @return KepegawaianSettingEnum[] */
    public function getSettingsProperty(): array
    {
        return KepegawaianSettingEnum::cases();
    }

    public function getCanEditSettingsProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_KEPEGAWAIAN->value);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_PENGATURAN_KEPEGAWAIAN->value), 403);

        if (! $this->companyId) {
            return;
        }

        foreach ($this->values as $key => $value) {
            $case = KepegawaianSettingEnum::from($key);
            // Checkbox uncheck tidak terkirim - normalisasi ke 0 (pola PembinaanSettingsPage).
            $normalized = $case->type() === SettingTypeEnum::BOOLEAN ? ($value == '1' ? '1' : '0') : $value;

            Setting::updateOrCreate(
                ['company_id' => $this->companyId, 'key' => $key],
                [
                    'module' => 'kepegawaian',
                    'type' => $case->type()->value,
                    'label' => $case->label(),
                    'description' => $case->helperText(),
                    'value' => $normalized,
                ]
            );
        }

        $companyName = $this->companies->firstWhere('id', $this->companyId)?->name ?? 'perusahaan ini';

        Notification::make()
            ->title("Pengaturan Kepegawaian {$companyName} berhasil disimpan")
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.kepegawaian.employment-settings-page');
    }
}
