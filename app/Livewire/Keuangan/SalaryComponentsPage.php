<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\GlobalPayrollSettingEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Livewire\Concerns\GatesCompanyFilterBySystem;
use App\Models\AuditLog;
use App\Models\Master\Companies;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Payroll\PayrollPeriod;
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
    use GatesCompanyFilterBySystem;

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

    public ?string $viewingAuditKey = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_KOMPONEN_GAJI->value), 403);

        // Default ke company sendiri kalau ada, biar familiar buat user scoped 1 company —
        // tapi tetap boleh pindah ke company manapun lewat tombol kiri (lihat getCompaniesProperty()),
        // KECUALI tidak punya permission lihat_perusahaan_lain -> dikunci ke company sendiri.
        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');
        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_KEUANGAN);

        $this->loadGlobalValues();
        $this->loadCompanyState();
    }

    // Gerbang di selectCompany() saja tidak cukup - Livewire property bisa di-$set langsung
    // dari request yang di-craft (lewat hook updated*, bukan lewat method public), jadi
    // companyId perlu divalidasi ulang di sini juga.
    public function updatedCompanyId(): void
    {
        $this->lockCompanyIdUnlessAllowed(CustomPermissionEnum::SISTEM_KEUANGAN);
    }

    public function selectCompany(string $companyId): void
    {
        abort_unless(Auth::user()->canFilterByCompanyForSystem(CustomPermissionEnum::SISTEM_KEUANGAN), 403);

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

        // get()->pluck() (Collection), BUKAN Builder::pluck() langsung - Builder::pluck()
        // bikin model sintetis yang cuma punya kolom 'value' tanpa 'key', accessor selektif
        // Setting::value() butuh 'key' di attribute yang sama buat tau harus decrypt atau
        // tidak (mis. bpjs_declared_value) - tanpa 'key' selalu dianggap plaintext.
        $companyRows = Setting::where('company_id', $this->companyId)->whereIn('key', $requiredKeys)->get(['key', 'value'])->pluck('value', 'key');
        $globalFallback = Setting::whereNull('company_id')->whereIn('key', $requiredKeys)->get(['key', 'value'])->pluck('value', 'key');

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
     * ini sudah digerbangi permission LIHAT_KOMPONEN_GAJI sendiri (dicek di mount()), jadi
     * pembatasan tambahan per-role di sini cuma bikin susah tanpa manfaat keamanan ekstra.
     * 1 company bisa punya susunan komponen gaji beda dari company lain.
     */
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

    /** Setting Global (GlobalPayrollSettingEnum) company_id-nya selalu NULL — riwayatnya
     * dicari di sana, bukan di company yang lagi dipilih kiri. */
    public function getAuditLogsProperty()
    {
        if (! $this->viewingAuditKey) {
            return collect();
        }

        $isGlobalKey = collect(GlobalPayrollSettingEnum::cases())->contains(fn ($e) => $e->value === $this->viewingAuditKey);

        return AuditLog::where('key', $this->viewingAuditKey)
            ->when(
                $isGlobalKey,
                fn ($q) => $q->whereNull('company_id'),
                fn ($q) => $q->where('company_id', $this->companyId)
            )
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /** @return array<int, array{component: PayrollComponentEnum, disabled: bool, alwaysOn: bool}> */
    public function getComponentsProperty(): array
    {
        $notYetAvailable = collect(PayrollComponentEnum::notYetAvailable())->map(fn ($c) => $c->value);
        $alwaysOn = collect(PayrollComponentEnum::alwaysOn())->map(fn ($c) => $c->value);

        return collect(PayrollComponentEnum::cases())
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
    public function getCanEditComponentsProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_KOMPONEN_GAJI->value);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_KOMPONEN_GAJI->value), 403);

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

        // Ganti bon_metode ke 'simple' - baris sisa dari mode 'detail' (>1 baris per periode)
        // digabung dulu, supaya Import Excel (updateOrCreate 1 baris) atau tampilan halaman
        // detail payroll gak salah baca baris yang ketinggalan. Lihat consolidateBonManualLines().
        if (($this->settingValues['bon_metode'] ?? null) === 'simple') {
            $this->consolidateBonManualLines($this->companyId);
        }

        $companyName = $this->companies->firstWhere('id', $this->companyId)?->name ?? 'perusahaan ini';

        Notification::make()
            ->title("Komponen gaji {$companyName} berhasil disimpan")
            ->success()
            ->send();
    }

    /**
     * Baris Bon >1 per (periode, user) - peninggalan mode 'detail' - digabung jadi 1 baris
     * (amount = sum semua, sisanya dihapus) begitu company pindah ke mode 'simple'. Tanpa ini,
     * updateOrCreate di BonImport (simple = 1 baris) cuma nimpa baris PERTAMA yang match,
     * sisanya tetap kehitung AbstractManualLineCalculator - dobel hitung senyap.
     *
     * Periode yang payroll-nya SUDAH dibayar (is_paid=true) dilewati - data historis yang sudah
     * dibayar tidak boleh diubah lagi, biarkan apa adanya.
     */
    private function consolidateBonManualLines(string $companyId): void
    {
        $periodIds = PayrollPeriod::where('company_id', $companyId)->pluck('id');

        $groups = PayrollManualLine::query()
            ->whereIn('payroll_period_id', $periodIds)
            ->where('component', PayrollComponentEnum::BON->value)
            ->get()
            ->groupBy(fn ($line) => "{$line->payroll_period_id}|{$line->employee_id}");

        foreach ($groups as $lines) {
            if ($lines->count() <= 1) {
                continue;
            }

            $first = $lines->first();

            $isPaid = Payroll::query()
                ->where('payroll_period_id', $first->payroll_period_id)
                ->where('employee_id', $first->employee_id)
                ->value('is_paid');

            if ($isPaid) {
                continue;
            }

            $first->update([
                'amount' => $lines->sum('amount'),
                'note' => "Digabung otomatis dari {$lines->count()} baris (ganti mode Bon ke Simple)",
            ]);

            $lines->skip(1)->each->delete();
        }
    }

    public function getCanEditGlobalSettingsProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::EDIT_KOMPONEN_GAJI_GLOBAL->value);
    }

    public function saveGlobal(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::EDIT_KOMPONEN_GAJI_GLOBAL->value), 403);

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
