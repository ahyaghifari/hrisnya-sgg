<?php

namespace App\Services\Payroll\Calculators;

use App\Enum\Payroll\GlobalPayrollSettingEnum;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;

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

    public function calculate(User $user, Payroll $payroll, PayrollPeriod $period, PayrollContext $context): void
    {
        $bpjsBasis = $user->profile?->pangkat?->bpjsBasis?->bpjs_basis;

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

        $amount = round((float) $basis * (float) $rate / 100, 2);

        if ($amount <= 0) {
            return;
        }

        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'name' => $this->componentLabel(),
            'type' => $this->componentType(),
            'quantity' => $rate,
            'unit' => 'persen',
            'unit_amount' => $basis,
            'amount' => $amount,
            'note' => "Basis: {$bpjsBasis}",
            'source_type' => $this->componentValue(),
        ]);

        $context->addDeduction($amount);
    }
}
