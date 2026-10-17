<?php

namespace App\Livewire\Pembinaan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Pembinaan\PembinaanSettingEnum;
use App\Enum\SettingTypeEnum;
use App\Models\AuditLog;
use App\Models\Master\Companies;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Pengaturan Pembinaan" - durasi SP1/SP2/SP3 per company
 * (planning/2026-09-23-sp-dan-merit-point.md §0, §3.1). Layout niru pola
 * persis EmploymentSettingsPage (Kepegawaian)/SikartapSettingsPage - kiri daftar company, kanan
 * form.
 *
 * Gerbang SENGAJA cuma SISTEM_PEMBINAAN_KARYAWAN (lihat+edit jadi satu), TIDAK pakai pola
 * LIHAT_PENGATURAN_XXX/EDIT_PENGATURAN_XXX seperti Kepegawaian/Sikartap/Absensi (instruksi user
 * 2026-09-23: reuse permission yang sudah ada & sudah dipegang HC lewat Coaching/SP, bukan bikin
 * permission baru yang perlu di-assign manual lagi).
 */
#[Layout('components.layout', ['title' => 'Pengaturan Pembinaan'])]
class PembinaanSettingsPage extends Component
{
    public ?string $companyId = null;

    /** @var array<string, string> PembinaanSettingEnum value => value (company terpilih) */
    public array $values = [];

    public ?string $viewingAuditKey = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);

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

        $keys = collect(PembinaanSettingEnum::cases())->map(fn ($e) => $e->value);

        $companyRows = Setting::where('company_id', $this->companyId)->whereIn('key', $keys)->pluck('value', 'key');
        $globalFallback = Setting::whereNull('company_id')->whereIn('key', $keys)->pluck('value', 'key');

        $this->values = collect(PembinaanSettingEnum::cases())
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

    /** @return PembinaanSettingEnum[] */
    public function getSettingsProperty(): array
    {
        return PembinaanSettingEnum::cases();
    }

    public function getCanEditSettingsProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value), 403);

        if (! $this->companyId) {
            return;
        }

        foreach ($this->values as $key => $value) {
            $case = PembinaanSettingEnum::from($key);

            // Checkbox yang di-uncheck tidak terkirim wire:model - normalisasi ke '0' biar
            // Setting::resolve() (filter_var FILTER_VALIDATE_BOOLEAN) tidak salah baca string
            // kosong/null sebagai truthy.
            $normalized = $case->type() === SettingTypeEnum::BOOLEAN ? ($value == '1' ? '1' : '0') : $value;

            Setting::updateOrCreate(
                ['company_id' => $this->companyId, 'key' => $key],
                [
                    'module' => 'pembinaan',
                    'type' => $case->type()->value,
                    'label' => $case->label(),
                    'description' => $case->helperText(),
                    'value' => $normalized,
                ]
            );
        }

        $companyName = $this->companies->firstWhere('id', $this->companyId)?->name ?? 'perusahaan ini';

        Notification::make()
            ->title("Pengaturan Pembinaan {$companyName} berhasil disimpan")
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.pembinaan.pembinaan-settings-page');
    }
}
