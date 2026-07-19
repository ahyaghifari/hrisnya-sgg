<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Imports\Payroll\BonImport;
use App\Imports\Payroll\IncentiveImport;
use App\Models\Master\Companies;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Halaman hasil Generate Payroll — dibangun custom (BUKAN reuse Filament Table) atas
 * permintaan eksplisit user, beda dari SalaryComponentsPage/IncentivesPage/dst di modul ini.
 * Filter perusahaan + periode, tombol "Lihat" ke halaman detail (PayrollDetailPage). Tombol
 * header (Import Insentif/Import Bon) muncul kondisional sesuai konfigurasi company yang lagi
 * dipilih — bukan statis buat semua company, dan buka modal (bukan navigasi ke halaman lain)
 * atas permintaan user. Input manual per baris (bukan import) cuma ada di halaman detail
 * payroll (PayrollDetailPage), tidak di sini.
 */
#[Layout('components.layout', ['title' => 'Payroll'])]
class PayrollsPage extends Component
{
    use WithFileUploads;

    public ?string $companyId = null;
    public ?string $periodId = null;

    public bool $showImportModal = false;
    public ?string $importType = null; // 'insentif' | 'bon'
    public ?string $importPeriodId = null;
    public $importFile;
    public array $importErrors = [];
    public ?int $importSuccessCount = null;

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

    /** Tombol Import Bon cuma tampil kalau komponen Bon aktif buat company ini. */
    public function getBonAktifProperty(): bool
    {
        if (! $this->companyId) {
            return false;
        }

        return CompanyPayrollComponent::query()
            ->where('company_id', $this->companyId)
            ->where('component', PayrollComponentEnum::BON->value)
            ->where('is_active', true)
            ->exists();
    }

    /** Cuma periode yang sudah digenerate (processed_at) yang boleh ditempeli import. */
    public function getImportPeriodsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return PayrollPeriod::query()
            ->where('company_id', $this->companyId)
            ->whereNotNull('processed_at')
            ->orderByDesc('start_date')
            ->get();
    }

    public function openImportModal(string $type): void
    {
        $this->importType = $type;
        $this->importPeriodId = null;
        $this->importFile = null;
        $this->importErrors = [];
        $this->importSuccessCount = null;
        $this->resetErrorBag();
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
    }

    public function runImport(): void
    {
        $this->importErrors = [];
        $this->importSuccessCount = null;

        $this->validate([
            'importPeriodId' => 'required',
            'importFile' => 'required|file|mimes:xlsx,xls,csv',
        ], [], [
            'importPeriodId' => 'periode payroll',
            'importFile' => 'file Excel',
        ]);

        if ($this->importType === 'insentif' && $this->insentifMetode !== 'import') {
            Notification::make()
                ->title('Metode insentif company ini bukan "import"')
                ->body('Ubah setting "Metode Insentif" di halaman Komponen Gaji dulu kalau memang mau pakai import Excel.')
                ->danger()
                ->send();

            return;
        }

        if ($this->importType === 'bon' && ! $this->bonAktif) {
            Notification::make()
                ->title('Komponen Bon belum aktif untuk company ini')
                ->body('Aktifkan dulu di halaman Komponen Gaji kalau memang mau pakai Bon.')
                ->danger()
                ->send();

            return;
        }

        $path = $this->importFile->store('temp-payroll-imports');
        $absolutePath = Storage::path($path);
        $import = $this->importType === 'insentif'
            ? new IncentiveImport($this->importPeriodId)
            : new BonImport($this->importPeriodId);

        try {
            Excel::import($import, $absolutePath);
        } finally {
            Storage::delete($path);
        }

        $this->importErrors = $import->getErrors();
        $this->importSuccessCount = $import->getSuccessCount();
        $this->reset('importFile');

        if ($this->importErrors === []) {
            Notification::make()
                ->title('Import berhasil')
                ->body("{$this->importSuccessCount} baris berhasil diimport. Jalankan Generate/Regenerate Payroll untuk periode ini supaya nominalnya masuk ke payroll.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title("{$this->importSuccessCount} baris berhasil, " . count($this->importErrors) . ' baris gagal')
                ->body('Lihat detail error di bawah form.')
                ->warning()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.keuangan.payrolls-page');
    }
}
