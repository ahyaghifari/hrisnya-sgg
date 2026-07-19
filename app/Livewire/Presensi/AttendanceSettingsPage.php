<?php

namespace App\Livewire\Presensi;

use App\Enum\Attendance\AttendanceSettingEnum;
use App\Enum\CustomPermissionEnum;
use App\Models\Master\Companies;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman "Pengaturan Absensi" — form per company untuk AttendanceSettingEnum (cutoff
 * periode, batas Cuti Tahunan, batas Izin gabungan). Layout niru pola kiri (daftar company) /
 * kanan (form) yang sama seperti SalaryComponentsPage, tanpa lapis toggle/component karena
 * semua setting di sini company-scoped flat. Lihat
 * planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §9.1.
 */
#[Layout('components.layout', ['title' => 'Pengaturan Absensi'])]
class AttendanceSettingsPage extends Component
{
    public ?string $companyId = null;

    /** @var array<string, string> AttendanceSettingEnum value => value (company terpilih) */
    public array $values = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_ABSENSI->value), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');

        $this->loadCompanyState();
    }

    public function selectCompany(string $companyId): void
    {
        $this->companyId = $companyId;
        $this->loadCompanyState();
    }

    private function loadCompanyState(): void
    {
        if (! $this->companyId) {
            $this->values = [];
            return;
        }

        $keys = collect(AttendanceSettingEnum::cases())->map(fn ($e) => $e->value);

        $companyRows = Setting::where('company_id', $this->companyId)->whereIn('key', $keys)->pluck('value', 'key');
        $globalFallback = Setting::whereNull('company_id')->whereIn('key', $keys)->pluck('value', 'key');

        $this->values = collect(AttendanceSettingEnum::cases())
            ->mapWithKeys(fn ($e) => [$e->value => (string) ($companyRows[$e->value] ?? $globalFallback[$e->value] ?? $e->default())])
            ->all();
    }

    /**
     * Semua company selalu tampil, terlepas dari canBrowseAllCompanies() — halaman ini sudah
     * digerbangi permission sistem_absensi sendiri (dicek di mount()), pola sama SalaryComponentsPage.
     */
    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    /** @return AttendanceSettingEnum[] */
    public function getSettingsProperty(): array
    {
        return AttendanceSettingEnum::cases();
    }

    public function save(): void
    {
        if (! $this->companyId) {
            return;
        }

        foreach ($this->values as $key => $value) {
            $case = AttendanceSettingEnum::from($key);

            Setting::updateOrCreate(
                ['company_id' => $this->companyId, 'key' => $key],
                [
                    'module' => 'attendance',
                    'type' => $case->type()->value,
                    'label' => $case->label(),
                    'description' => $case->helperText(),
                    'value' => $value,
                ]
            );
        }

        $companyName = $this->companies->firstWhere('id', $this->companyId)?->name ?? 'perusahaan ini';

        Notification::make()
            ->title("Pengaturan Absensi {$companyName} berhasil disimpan")
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.presensi.attendance-settings-page');
    }
}
