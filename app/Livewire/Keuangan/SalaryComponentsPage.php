<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\GlobalPayrollSettingEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Komponen Gaji" — 3 lapis setting (lihat
 * planning/2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md & diskusi lanjutan):
 * 1) GlobalPayrollSettingEnum — selalu global (company_id NULL), 1 panel di atas, tidak
 *    berubah walau company kiri diganti.
 * 2) PayrollComponentEnum::requiredSettings() — wajib per komponen, SELALU company-scoped,
 *    muncul otomatis begitu komponennya di-expand (klik baris).
 * 3) Sisa setting module=payroll yang belum "diklaim" #1/#2 — "Pengaturan Lainnya", tetap
 *    company-scoped, buat ekstensibilitas ke depan.
 *
 * Layout kiri (daftar company) / kanan (daftar komponen) niru pola .doc-type-layout yang
 * sudah ada di halaman Data Diri (tab Dokumen) — dipilih user sebagai referensi desain.
 */
#[Layout('components.layout', ['title' => 'Komponen Gaji'])]
class SalaryComponentsPage extends Component
{
    public ?string $companyId = null;
    public ?string $expandedComponent = null;

    /** @var array<string, bool> component value => aktif/tidak (company terpilih) */
    public array $active = [];

    /** @var array<string, string> setting key => value, WAJIB per komponen (company terpilih) */
    public array $settingValues = [];

    /** @var array<string, string> setting key => value, "Pengaturan Lainnya" (company terpilih) */
    public array $flexibleValues = [];

    /** @var array<string, string> GlobalPayrollSettingEnum value => value (selalu global) */
    public array $globalValues = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);

        // Default ke company sendiri kalau ada, biar familiar buat user scoped 1 company —
        // tapi tetap boleh pindah ke company manapun lewat tombol kiri (lihat getCompaniesProperty()).
        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');

        $this->loadGlobalValues();
        $this->loadCompanyState();
    }

    public function selectCompany(string $companyId): void
    {
        $this->companyId = $companyId;
        $this->expandedComponent = null;
        $this->loadCompanyState();
    }

    public function toggleExpand(string $component): void
    {
        $this->expandedComponent = $this->expandedComponent === $component ? null : $component;
    }

    /**
     * Checkbox `active.*` pakai wire:model.live (bukan method custom) — hook bawaan Livewire
     * ini yang nangkep perubahannya. Begitu komponen DINYALAIN (bukan dimatiin) DAN punya
     * setting wajib, panel Pengaturan langsung ke-buka otomatis — user tidak perlu klik panah
     * bawah terpisah buat lihat apa yang perlu diisi.
     */
    public function updated(string $name, mixed $value): void
    {
        if (! str_starts_with($name, 'active.') || ! $value) {
            return;
        }

        $component = substr($name, strlen('active.'));
        $enum = PayrollComponentEnum::tryFrom($component);

        if ($enum && $enum->requiredSettings() !== []) {
            $this->expandedComponent = $component;
        }
    }

    private function loadGlobalValues(): void
    {
        $existing = Setting::whereNull('company_id')
            ->whereIn('key', collect(GlobalPayrollSettingEnum::cases())->map(fn ($e) => $e->value))
            ->pluck('value', 'key');

        $this->globalValues = collect(GlobalPayrollSettingEnum::cases())
            ->mapWithKeys(fn ($e) => [$e->value => (string) ($existing[$e->value] ?? '0')])
            ->all();
    }

    private function loadCompanyState(): void
    {
        if (! $this->companyId) {
            $this->active = [];
            $this->settingValues = [];
            $this->flexibleValues = [];
            return;
        }

        $existingToggles = CompanyPayrollComponent::query()
            ->where('company_id', $this->companyId)
            ->pluck('is_active', 'component');

        $this->active = collect(PayrollComponentEnum::cases())
            ->mapWithKeys(fn (PayrollComponentEnum $c) => [$c->value => (bool) ($existingToggles[$c->value] ?? false)])
            ->all();

        $requiredKeys = collect(PayrollComponentEnum::cases())
            ->flatMap(fn (PayrollComponentEnum $c) => $c->requiredSettings())
            ->unique()
            ->values();

        $companyRows = Setting::where('company_id', $this->companyId)->whereIn('key', $requiredKeys)->pluck('value', 'key');
        $globalFallback = Setting::whereNull('company_id')->whereIn('key', $requiredKeys)->pluck('value', 'key');

        $this->settingValues = $requiredKeys
            ->mapWithKeys(fn ($key) => [$key => (string) ($companyRows[$key] ?? $globalFallback[$key] ?? '')])
            ->all();

        $claimedKeys = collect(GlobalPayrollSettingEnum::cases())->map(fn ($e) => $e->value)->merge($requiredKeys);

        $this->flexibleValues = Setting::query()
            ->where('module', 'payroll')
            ->where(fn ($q) => $q->where('company_id', $this->companyId)->orWhereNull('company_id'))
            ->whereNotIn('key', $claimedKeys)
            ->orderByRaw('company_id IS NULL')
            ->get()
            ->unique('key')
            ->mapWithKeys(fn ($s) => [$s->key => (string) $s->value])
            ->all();
    }

    /**
     * Semua company selalu tampil di sini, terlepas dari canBrowseAllCompanies() — halaman
     * ini sudah digerbangi permission sistem_keuangan sendiri (dicek di mount()), jadi
     * pembatasan tambahan per-role di sini cuma bikin susah tanpa manfaat keamanan ekstra.
     * 1 company bisa punya susunan komponen gaji beda dari company lain.
     */
    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    /** @return array<int, array{component: PayrollComponentEnum, disabled: bool, alwaysOn: bool}> */
    public function getComponentsProperty(): array
    {
        $notYetAvailable = collect(PayrollComponentEnum::notYetAvailable())->map(fn ($c) => $c->value);
        $alwaysOn = collect(PayrollComponentEnum::alwaysOn())->map(fn ($c) => $c->value);

        return collect(PayrollComponentEnum::cases())
            // FEE_CLAIM murni pass-through tanpa setting apa pun — tidak perlu tampil di daftar.
            ->reject(fn (PayrollComponentEnum $c) => $c === PayrollComponentEnum::FEE_CLAIM)
            ->map(fn (PayrollComponentEnum $c) => [
                'component' => $c,
                'disabled' => $notYetAvailable->contains($c->value),
                'alwaysOn' => $alwaysOn->contains($c->value),
            ])
            ->values()
            ->all();
    }

    public function getFlexibleSettingMetaProperty()
    {
        return Setting::query()
            ->whereIn('key', array_keys($this->flexibleValues))
            ->get()
            ->unique('key')
            ->keyBy('key');
    }

    public function getGlobalSettingMetaProperty()
    {
        return Setting::whereNull('company_id')
            ->whereIn('key', collect(GlobalPayrollSettingEnum::cases())->map(fn ($e) => $e->value))
            ->get()
            ->keyBy('key');
    }

    public function getRequiredSettingMetaProperty()
    {
        return Setting::query()
            ->whereIn('key', array_keys($this->settingValues))
            ->get()
            ->unique('key')
            ->keyBy('key');
    }

    /**
     * Ambil metadata (label/tipe/modul/keterangan) dari baris manapun yang sudah ada dengan
     * key sama — supaya bikin baris override company baru tidak perlu dup metadata manual.
     */
    private function upsertSetting(?string $companyId, string $key, string $value): void
    {
        $template = Setting::where('key', $key)->first();

        if (! $template) {
            return;
        }

        Setting::updateOrCreate(
            ['company_id' => $companyId, 'key' => $key],
            [
                'module' => $template->module,
                'type' => $template->type,
                'label' => $template->label,
                'description' => $template->description,
                'value' => $value,
            ]
        );
    }

    /**
     * Sengaja dipisah dari saveGlobal() — 1 tombol ini CUMA nyimpen company yang lagi
     * dipilih (toggle, setting wajib per komponen, pengaturan lainnya), TIDAK ikut nulis
     * ulang panel Global tiap submit. User eksplisit minta simpan per company, bukan sekali
     * tombol nyimpen segalanya.
     */
    public function save(): void
    {
        if (! $this->companyId) {
            return;
        }

        foreach ($this->active as $component => $isActive) {
            CompanyPayrollComponent::updateOrCreate(
                ['company_id' => $this->companyId, 'component' => $component],
                ['is_active' => $isActive]
            );
        }

        foreach ($this->settingValues as $key => $value) {
            $this->upsertSetting($this->companyId, $key, $value);
        }

        foreach ($this->flexibleValues as $key => $value) {
            $this->upsertSetting($this->companyId, $key, $value);
        }

        $companyName = $this->companies->firstWhere('id', $this->companyId)?->name ?? 'perusahaan ini';

        Notification::make()
            ->title("Komponen gaji {$companyName} berhasil disimpan")
            ->success()
            ->send();
    }

    public function saveGlobal(): void
    {
        foreach ($this->globalValues as $key => $value) {
            $this->upsertSetting(null, $key, $value);
        }

        Notification::make()
            ->title('Pengaturan global berhasil disimpan')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.salary-components-page');
    }
}
