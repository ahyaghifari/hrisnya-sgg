<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\GlobalPayrollSettingEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\Employee\Employee;

/**
 * Basis potongan dibaca dari `pangkat.bpjsBasis` (tabel `pangkat_bpjs_bases`, 1:1 ke
 * m_pangkats, diisi lewat halaman Komponen Gaji — bukan form Pangkat biasa). Belum
 * di-mapping (relasi kosong) = auto SKIP, tidak ada potongan sama sekali — bukan fallback ke
 * UMP. 'ump' & 'declared_value' sama-sama Setting company-scoped (fallback ke baris global
 * kalau company belum override) — bukan GlobalPayrollSettingEnum, karena UMP beda tiap company.
 */
abstract class AbstractBpjsCalculator implements PayrollComponentCalculator
{
    abstract protected function rateSetting(): GlobalPayrollSettingEnum;
    abstract protected function componentValue(): string;
    abstract protected function componentLabel(): string;
    abstract protected function componentType(): string;

    /** Dikali ke amount akhir — cuma BPJS Kesehatan yang override ini (lihat tanggungan). */
    protected function multiplier(Employee $employee, Payroll $payroll): float
    {
        return 1.0;
    }

    public function calculate(Employee $employee, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $bpjsBasis = $employee->pangkat?->bpjsBasis?->bpjs_basis;

        if (! $bpjsBasis) {
            return;
        }

        $basis = match ($bpjsBasis) {
            'ump' => Setting::resolve('ump', $period->company_id),
            'declared_value' => Setting::resolve('bpjs_declared_value', $period->company_id),
            default => null,
        };

        if (! $basis || $basis <= 0) {
            return;
        }

        $rate = Setting::resolve($this->rateSetting()->value, null);

        if (! $rate || $rate <= 0) {
            return;
        }

        $multiplier = $this->multiplier($employee, $payroll);

        $amount = round((float) $basis * (float) $rate / 100 * $multiplier, 2);

        if ($amount <= 0) {
            return;
        }

        $note = "Basis: {$bpjsBasis}";

        if ($multiplier > 1.0) {
            $note .= ' (x' . rtrim(rtrim(number_format($multiplier, 2, '.', ''), '0'), '.') . ' tanggungan)';
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => PayrollComponentEnum::from($this->componentValue())->defaultRowName(null),
            'type' => $this->componentType(),
            'quantity' => $rate,
            'unit' => 'persen',
            'unit_amount' => $basis,
            'amount' => $amount,
            'note' => $note,
            'source_type' => $this->componentValue(),
        ]);

        $context->addDeduction($amount);
    }
}
