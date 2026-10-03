<?php

namespace App\Livewire\Keuangan;

use App\Enum\CustomPermissionEnum;
use App\Exports\Payroll\PajakGajiPokokExport;
use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Halaman "Pajak (%)" — potongan 3 kolom dari PayrollSlipRekapExport (Gaji Dasar, % Kenaikan,
 * Gaji Pokok final) buat finance yang cuma butuh basis perhitungan PPh21, tanpa buka rekap gaji
 * lengkap. Sama pola filter company+period dengan PayrollsPage, TAPI digerbangi permission
 * SENDIRI (LIHAT_PAJAK, bukan ViewAny:Payroll) - supaya bisa dikasih ke role finance yang lebih
 * terbatas (2026-08-22, instruksi user).
 */
#[Layout('components.layout', ['title' => 'Pajak'])]
class PajakPage extends Component
{
    public ?string $companyId = null;
    public ?string $periodId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_PAJAK->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;
    }

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

        return PayrollPeriod::acrossCompanies()->where('company_id', $this->companyId)->orderByDesc('start_date')->get();
    }

    public function getPayrollsProperty()
    {
        if (! $this->periodId) {
            return collect();
        }

        return Payroll::query()
            ->where('payroll_period_id', $this->periodId)
            ->with([
                'user.profile.pangkat.pangkatSalary',
                'user.profile.kelas',
                'user.profile.employeeType',
                'user.profile.jobPosition.orgStructure.parent.parent',
                'user.profile.jobPosition.orgStructure.parent.parent',
                'payrollPeriod' => fn ($q) => $q->withoutGlobalScope('company'),
                'items',
            ])
            ->get();
    }

    public function exportExcel()
    {
        abort_unless($this->periodId, 400, 'Pilih periode dulu sebelum export.');

        $period = PayrollPeriod::acrossCompanies()->findOrFail($this->periodId);
        $filename = 'Pajak-' . $period->start_date->format('mY') . '.xlsx';

        return Excel::download(new PajakGajiPokokExport($this->payrolls, $this->companyId), $filename);
    }

    public function render()
    {
        return view('livewire.keuangan.pajak-page');
    }
}
