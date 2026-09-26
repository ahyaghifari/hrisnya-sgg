<?php

namespace App\Livewire\Keuangan;

use App\Models\Payroll\Payroll;
use App\Services\Payroll\PayrollService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Padanan self-service dari PayrollDetailPage (admin/finance) — read-only, tanpa aksi ubah
 * apapun (tidak ada tandai-dibayar/regenerate/manual-line, karyawan cuma boleh lihat &
 * unduh/preview slip miliknya sendiri yang SUDAH dibayar). Sengaja komponen terpisah, bukan
 * reuse PayrollDetailPage dengan flag "readOnly" — halaman itu penuh method admin (markAsPaid,
 * saveManualLine, dst) yang tidak boleh sekalipun bisa dipanggil karyawan biasa.
 */
#[Layout('components.layout', ['title' => 'Slip Gaji'])]
class MyPayrollSlipPage extends Component
{
    public Payroll $payroll;

    /** Selalu tersembunyi tiap halaman dibuka (sengaja TIDAK di-persist di session seperti
     * MasksMoney di halaman admin). Nominal tidak dirender ke HTML saat tersembunyi. */
    public bool $hideMoney = true;

    public function toggleMoney(): void
    {
        $this->hideMoney = ! $this->hideMoney;
    }

    public function rp(?float $amount): string
    {
        return $this->hideMoney
            ? "Rp\u{00A0}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}"
            : 'Rp' . number_format((float) $amount, 0, ',', '.');
    }

    public function mount(Payroll $payroll): void
    {
        abort_unless(
            $payroll->user_id === Auth::id() && $payroll->is_paid,
            403
        );

        $this->payroll = $payroll->load(['payrollPeriod.company']);
    }

    /** @return array{earnings: array, deductions: array} */
    public function getComponentRowsProperty(): array
    {
        return app(PayrollService::class)->componentRows($this->payroll);
    }

    public function downloadSlip()
    {
        return app(PayrollService::class)->download($this->payroll);
    }

    public function render()
    {
        return view('livewire.keuangan.my-payroll-slip-page');
    }
}
