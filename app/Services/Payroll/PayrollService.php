<?php

namespace App\Services\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Enum\Payroll\PayrollComponentTypeEnum;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\Calculators\FeeClaimCalculator;
use App\Services\Payroll\Calculators\PayrollCalculatorRegistry;
use App\Services\Payroll\Calculators\PayrollContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Orchestrator tipis — logika perhitungan tiap komponen ada di
 * app/Services/Payroll/Calculators/*.php (Registry+Strategy+Config, lihat
 * planning/2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md). Method lama
 * handleXxxComponent() sudah dipindah semua ke Calculator masing-masing.
 */
class PayrollService
{
    public function download(Payroll $payroll): StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.payroll.payroll', [
            'payroll' => $payroll,
        ]);

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->stream();
            },
            'slip-gaji-' . str_replace(' ', '-', strtolower($payroll->user->name)) . '.pdf',
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }

    public function regeneratePayroll(PayrollPeriod $period): void
    {
        DB::transaction(function () use ($period) {
            // Delete only unpaid payrolls; paid payrolls are kept as-is.
            // payroll_items will be removed automatically via cascadeOnDelete.
            Payroll::query()
                ->where('payroll_period_id', $period->id)
                ->where('is_paid', false)
                ->delete();

            // Re-run generation — payrollAlreadyExists() will skip paid ones.
            $this->generatePayroll($period);
        });
    }

    public function generatePayroll(PayrollPeriod $period): void
    {
        DB::transaction(function () use ($period) {
            $users = User::query()
                ->where('company_id', $period->company_id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->get();

            $activeComponents = CompanyPayrollComponent::query()
                ->where('company_id', $period->company_id)
                ->where('is_active', true)
                ->get();

            foreach ($users as $user) {
                if ($this->payrollAlreadyExists($user, $period)) {
                    continue;
                }

                $payroll = Payroll::create([
                    'user_id' => $user->id,
                    'payroll_period_id' => $period->id,
                ]);

                $context = new PayrollContext();

                // Urutan WAJIB: semua EARNING dulu -> Fee Claim (earning, always-on) -> semua
                // DEDUCTION kecuali Izin Seharian -> Izin Seharian terakhir. Izin Seharian
                // butuh $context->grossSalary (T8) yang sudah final, lihat planning Fase 2 §6.
                $earning = $activeComponents->filter(
                    fn (CompanyPayrollComponent $c) => PayrollComponentEnum::tryFrom($c->component)?->type() === PayrollComponentTypeEnum::EARNING
                );
                $deduction = $activeComponents->filter(
                    fn (CompanyPayrollComponent $c) => PayrollComponentEnum::tryFrom($c->component)?->type() === PayrollComponentTypeEnum::DEDUCTION
                        && $c->component !== PayrollComponentEnum::IZIN_SEHARIAN->value
                );
                $izinSeharian = $activeComponents->first(
                    fn (CompanyPayrollComponent $c) => $c->component === PayrollComponentEnum::IZIN_SEHARIAN->value
                );

                foreach ($earning as $companyComponent) {
                    $this->runComponent($companyComponent, $user, $payroll, $period, $context);
                }

                // Always-on, tidak digate lewat company_payroll_components (lihat
                // PayrollComponentEnum::alwaysOn() dan planning Fase 1 §3.1).
                app(FeeClaimCalculator::class)->calculate($user, $payroll, $period, $context);

                foreach ($deduction as $companyComponent) {
                    $this->runComponent($companyComponent, $user, $payroll, $period, $context);
                }

                if ($izinSeharian) {
                    $this->runComponent($izinSeharian, $user, $payroll, $period, $context);
                }

                $payroll->update([
                    'gross_salary' => $context->grossSalary,
                    'total_deductions' => $context->totalDeductions,
                    'net_salary' => $context->grossSalary - $context->totalDeductions,
                ]);
            }

            $period->update([
                'processed_at' => now(),
            ]);
        });
    }

    private function payrollAlreadyExists(User $user, PayrollPeriod $period): bool
    {
        return Payroll::query()
            ->where('user_id', $user->id)
            ->where('payroll_period_id', $period->id)
            ->exists();
    }

    private function runComponent(
        CompanyPayrollComponent $companyComponent,
        User $user,
        Payroll $payroll,
        PayrollPeriod $period,
        PayrollContext $context
    ): void {
        if (! $companyComponent->isEligibleForEmployeeType($user->profile?->employee_type_id)) {
            return;
        }

        $component = PayrollComponentEnum::tryFrom($companyComponent->component);

        if (! $component) {
            return;
        }

        $calculatorClass = PayrollCalculatorRegistry::resolve($component);

        if (! $calculatorClass) {
            return; // belum ada Calculator (§2.2 planning Fase 1), skip aman
        }

        app($calculatorClass)->calculate($user, $payroll, $period, $context);
    }
}
