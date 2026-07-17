<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Imports\Payroll\IncentiveImport;
use App\Models\Master\Companies;
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
 * Insentif RSU — cuma berlaku kalau setting `insentif_metode` company terpilih = "import"
 * (lihat halaman Komponen Gaji). Import HARUS dilakukan SETELAH payroll periode itu
 * digenerate (menempel PayrollItem ke Payroll yang sudah ada, lihat IncentiveImport) — bukan
 * sebelum, beda dari alur "master" (Holding) yang input manual sebelum generate.
 */
#[Layout('components.layout', ['title' => 'Import Insentif'])]
class ImportIncentivesPage extends Component
{
    use WithFileUploads;

    public ?string $companyId = null;
    public ?string $payrollPeriodId = null;
    public $file;

    public array $importErrors = [];
    public ?int $importSuccessCount = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEUANGAN->value), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');
    }

    public function updatedCompanyId(): void
    {
        $this->payrollPeriodId = null;
        $this->resetResult();
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    /** Cuma periode yang sudah digenerate (processed_at) yang boleh ditempeli insentif import. */
    public function getPeriodsProperty()
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

    public function getMetodeProperty(): string
    {
        return (string) Setting::resolve('insentif_metode', $this->companyId, default: 'master');
    }

    public function import(): void
    {
        $this->resetResult();

        $this->validate([
            'payrollPeriodId' => 'required',
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ], [], [
            'payrollPeriodId' => 'periode payroll',
            'file' => 'file Excel',
        ]);

        if ($this->metode !== 'import') {
            Notification::make()
                ->title('Metode insentif company ini bukan "import"')
                ->body('Ubah setting "Metode Insentif" di halaman Komponen Gaji dulu kalau memang mau pakai import Excel.')
                ->danger()
                ->send();

            return;
        }

        $path = $this->file->store('temp-incentive-imports');
        $absolutePath = Storage::path($path);
        $import = new IncentiveImport($this->payrollPeriodId);

        try {
            Excel::import($import, $absolutePath);
        } finally {
            Storage::delete($path);
        }

        $this->importErrors = $import->getErrors();
        $this->importSuccessCount = $import->getSuccessCount();
        $this->reset('file');

        if ($this->importErrors === []) {
            Notification::make()
                ->title('Import berhasil')
                ->body("{$this->importSuccessCount} baris berhasil diimport.")
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

    private function resetResult(): void
    {
        $this->importErrors = [];
        $this->importSuccessCount = null;
    }

    public function render()
    {
        return view('livewire.keuangan.import-incentives-page');
    }
}
