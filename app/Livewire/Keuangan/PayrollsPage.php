<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Master\Companies;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman hasil Generate Payroll — dibangun custom (BUKAN reuse Filament Table) atas
 * permintaan eksplisit user, beda dari SalaryComponentsPage/IncentivesPage/dst di modul ini.
 * Filter perusahaan + periode, tombol "Lihat" ke halaman detail (PayrollDetailPage). Tombol
 * header (Import Insentif/Koperasi/Bon) muncul kondisional sesuai konfigurasi company yang
 * lagi dipilih — bukan statis buat semua company.
 */
#[Layout('components.layout', ['title' => 'Payroll'])]
class PayrollsPage extends Component
{
    public ?string $companyId = null;
    public ?string $periodId = null;

    public bool $showManualLineModal = false;
    public ?string $manualLineComponent = null; // 'koperasi' | 'bon'
    public ?string $manualLineUserId = null;
    public ?string $manualLineAmount = null;
    public ?string $manualLineNote = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');
    }

    /**
     * Cari/sortir/pagination baris yang sudah tampil ditangani List.js di browser (lihat
     * resources/js/app.js) — tidak roundtrip ke server lagi. Company/period tetap lewat
     * Livewire karena itu benar-benar ganti data dari database. Event ini memberitahu List.js
     * kalau barisnya baru saja diganti Livewire, supaya index pencariannya di-refresh.
     */
    public function updatedCompanyId(): void
    {
        $this->periodId = null;
        $this->dispatch('list-table:refresh');
    }

    public function updatedPeriodId(): void
    {
        $this->dispatch('list-table:refresh');
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getPeriodsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return PayrollPeriod::where('company_id', $this->companyId)->orderByDesc('start_date')->get();
    }

    public function getPayrollsProperty()
    {
        return Payroll::query()
            ->when($this->companyId, fn ($q) => $q->whereHas('payrollPeriod', fn ($q2) => $q2->where('company_id', $this->companyId)))
            ->when($this->periodId, fn ($q) => $q->where('payroll_period_id', $this->periodId))
            ->with(['user', 'payrollPeriod'])
            ->orderByDesc('created_at')
            ->get();
    }

    /** Metode insentif company terpilih — tombol Import Insentif cuma tampil kalau 'import'. */
    public function getInsentifMetodeProperty(): string
    {
        return (string) Setting::resolve('insentif_metode', $this->companyId, default: 'master');
    }

    public function getKoperasiAktifProperty(): bool
    {
        return $this->isComponentActive(PayrollComponentEnum::KOPERASI->value);
    }

    public function getBonAktifProperty(): bool
    {
        return $this->isComponentActive(PayrollComponentEnum::BON->value);
    }

    private function isComponentActive(string $component): bool
    {
        if (! $this->companyId) {
            return false;
        }

        return CompanyPayrollComponent::query()
            ->where('company_id', $this->companyId)
            ->where('component', $component)
            ->where('is_active', true)
            ->exists();
    }

    public function getEmployeesForManualLineProperty()
    {
        return $this->companyId
            ? User::where('company_id', $this->companyId)->where('is_active', true)->orderBy('name')->get()
            : collect();
    }

    public function openManualLineModal(string $component): void
    {
        $this->manualLineComponent = $component;
        $this->manualLineUserId = null;
        $this->manualLineAmount = null;
        $this->manualLineNote = null;
        $this->resetErrorBag();
        $this->showManualLineModal = true;
    }

    public function closeManualLineModal(): void
    {
        $this->showManualLineModal = false;
    }

    public function saveManualLine(): void
    {
        $this->validate([
            'periodId' => 'required',
            'manualLineUserId' => 'required',
            'manualLineAmount' => 'required|numeric|min:0.01',
        ], [], [
            'periodId' => 'periode payroll',
            'manualLineUserId' => 'karyawan',
            'manualLineAmount' => 'nominal',
        ]);

        PayrollManualLine::create([
            'payroll_period_id' => $this->periodId,
            'user_id' => $this->manualLineUserId,
            'component' => $this->manualLineComponent,
            'amount' => $this->manualLineAmount,
            'note' => $this->manualLineNote,
            'created_by' => Auth::id(),
        ]);

        $this->showManualLineModal = false;

        $label = $this->manualLineComponent === PayrollComponentEnum::KOPERASI->value ? 'Koperasi' : 'Bon';

        Notification::make()
            ->title("{$label} berhasil dicatat")
            ->body('Akan masuk ke payroll berikutnya kali Generate/Regenerate dijalankan untuk periode ini.')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.keuangan.payrolls-page');
    }
}
