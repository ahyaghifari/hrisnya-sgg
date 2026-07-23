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
 * payroll (PayrollDetailPage), tidak di sini. Cari/company/period semuanya lewat Livewire
 * (server-side) — SEBELUMNYA cari pakai List.js di browser, tapi List.js kedapatan mengunci
 * baris tbody-nya sendiri sehingga tidak ikut ke-morph Livewire begitu company/periode ganti
 * (baris karyawan lama nyangkut terus), jadi dicabut.
 */
#[Layout('components.layout', ['title' => 'Payroll'])]
class PayrollsPage extends Component
{
    use WithFileUploads;

    public ?string $companyId = null;
    public ?string $periodId = null;
    public string $search = '';

    public bool $showImportModal = false;
    public ?string $importType = null; // 'insentif' | 'bon'
    public ?string $importPeriodId = null;
    public $importFile;
    public array $importErrors = [];
    public ?int $importSuccessCount = null;

    /** Baris hasil parsing file, sebelum disimpan — lihat previewImport()/confirmImport(). */
    public array $importPreviewRows = [];
    public ?string $importStoredPath = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

    /** Ganti company -> pilihan periode lama tidak relevan lagi. */
    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        $this->periodId = null;
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

    /** Cari nama/NIP — server-side (bukan List.js, lihat catatan kelas ini). */
    public function getPayrollsProperty()
    {
        $search = trim($this->search);

        return Payroll::query()
            ->when($this->companyId, fn ($q) => $q->whereHas('payrollPeriod', fn ($q2) => $q2->where('company_id', $this->companyId)))
            ->when($this->periodId, fn ($q) => $q->where('payroll_period_id', $this->periodId))
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('nip', 'like', "%{$search}%")))
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
        $this->importPreviewRows = [];
        $this->discardStoredImportFile();
        $this->resetErrorBag();
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->discardStoredImportFile();
    }

    /** Periode ganti setelah file sudah dipratinjau -> pratinjau lama tidak valid lagi, ulang. */
    public function updatedImportPeriodId(): void
    {
        if ($this->importStoredPath !== null) {
            $this->previewImport();
        }
    }

    /** File baru dipilih (wire:model) -> langsung parse & tampilkan pratinjau, JANGAN simpan dulu. */
    public function updatedImportFile(): void
    {
        $this->importErrors = [];
        $this->importSuccessCount = null;
        $this->previewImport();
    }

    private function previewImport(): void
    {
        $this->importPreviewRows = [];
        $this->discardStoredImportFile();

        $this->validate([
            'importPeriodId' => 'required',
            'importFile' => 'required|file|mimes:xlsx,xls,csv',
        ], [], [
            'importPeriodId' => 'periode payroll',
            'importFile' => 'file Excel',
        ]);

        if (! $this->importGuardPasses()) {
            return;
        }

        $this->importStoredPath = $this->importFile->store('temp-payroll-imports');
        $import = $this->makeImport(preview: true);
        Excel::import($import, Storage::path($this->importStoredPath));

        $this->importPreviewRows = $import->getPreview();
    }

    /** Tombol "Simpan" di pratinjau -> baru di sini datanya benar-benar ditulis ke database. */
    public function confirmImport(): void
    {
        if ($this->importStoredPath === null || ! $this->importGuardPasses()) {
            return;
        }

        $import = $this->makeImport(preview: false);

        try {
            Excel::import($import, Storage::path($this->importStoredPath));
        } finally {
            $this->discardStoredImportFile();
        }

        $this->importErrors = $import->getErrors();
        $this->importSuccessCount = $import->getSuccessCount();
        $this->importPreviewRows = [];
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

    private function makeImport(bool $preview): IncentiveImport|BonImport
    {
        return $this->importType === 'insentif'
            ? new IncentiveImport($this->importPeriodId, $preview)
            : new BonImport($this->importPeriodId, $preview);
    }

    private function importGuardPasses(): bool
    {
        if ($this->importType === 'insentif' && $this->insentifMetode !== 'import') {
            Notification::make()
                ->title('Metode insentif company ini bukan "import"')
                ->body('Ubah setting "Metode Insentif" di halaman Komponen Gaji dulu kalau memang mau pakai import Excel.')
                ->danger()
                ->send();

            return false;
        }

        if ($this->importType === 'bon' && ! $this->bonAktif) {
            Notification::make()
                ->title('Komponen Bon belum aktif untuk company ini')
                ->body('Aktifkan dulu di halaman Komponen Gaji kalau memang mau pakai Bon.')
                ->danger()
                ->send();

            return false;
        }

        return true;
    }

    private function discardStoredImportFile(): void
    {
        if ($this->importStoredPath !== null) {
            Storage::delete($this->importStoredPath);
            $this->importStoredPath = null;
        }
    }

    public function render()
    {
        return view('livewire.keuangan.payrolls-page');
    }
}
