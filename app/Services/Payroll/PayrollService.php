<?php

namespace App\Services\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Enum\Payroll\PayrollComponentTypeEnum;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Setting;
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
            'rows' => $this->componentRows($payroll),
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

    /**
     * Beda dari download() — dompdf stream() defaultnya Content-Disposition: inline (buka di
     * tab baru/PDF viewer browser), TIDAK dibungkus response()->streamDownload() yang selalu
     * memaksa attachment. Dipakai tombol "Preview Slip Gaji" (link biasa target=_blank, bukan
     * aksi Livewire) — lihat routes/web.php keuangan.payroll.preview.
     */
    public function preview(Payroll $payroll)
    {
        $pdf = Pdf::loadView('pdf.payroll.payroll', [
            'payroll' => $payroll,
            'rows' => $this->componentRows($payroll),
        ]);

        return $pdf->stream('slip-gaji-' . str_replace(' ', '-', strtolower($payroll->user->name)) . '.pdf');
    }

    /**
     * Seluruh komponen gaji yang TERDAFTAR aktif untuk company payroll ini (halaman Komponen
     * Gaji) + yang selalu aktif (Fee Claim dkk) — bukan cuma yang kebetulan menghasilkan
     * PayrollItem. Komponen yang tidak menghasilkan apa-apa buat karyawan ini tetap tampil
     * dengan Rp 0. Dipakai bareng oleh halaman detail portal (PayrollDetailPage) dan PDF slip
     * gaji, supaya keduanya selalu menampilkan daftar komponen yang sama persis.
     *
     * @return array{earnings: array<int, array{label: string, note: ?string, amount: float, hasValue: bool}>, deductions: array<int, array{label: string, note: ?string, amount: float, hasValue: bool}>}
     */
    public function componentRows(Payroll $payroll): array
    {
        $companyId = $payroll->payrollPeriod->company_id;

        $activeComponents = CompanyPayrollComponent::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->pluck('component');

        // Sengaja TIDAK ikut PayrollComponentEnum::alwaysOn() di sini — itu cuma soal
        // Calculator-nya jalan tanpa dicek toggle, bukan berarti komponennya "ada" buat
        // company ini. Fee Claim/Pajak Jasa Dokter cuma boleh muncul di slip kalau memang
        // menghasilkan angka nyata (ditangani di bagian bawah), bukan sebagai baris Rp 0
        // untuk company yang tidak punya baris fee_items sama sekali (mis. Holding).
        $applicable = collect(PayrollComponentEnum::cases())
            ->filter(fn (PayrollComponentEnum $c) => $activeComponents->contains($c->value));

        $items = $payroll->items;

        $sums = $items->whereNotNull('source_type')
            ->groupBy('source_type')
            ->map(fn ($group) => (float) $group->sum('amount'));

        // Anotasi kecil di sebelah label — nunjukin aturan yang lagi berlaku (bukan cuma
        // ngasih angka, tapi kenapa angkanya segitu), diminta eksplisit buat Lembur & BPJS.
        $noteFor = function (PayrollComponentEnum $c) use ($companyId): ?string {
            return match ($c) {
                PayrollComponentEnum::LEMBUR => 'Dihitung > ' . (int) Setting::resolve('lembur_minimum_jam', $companyId, default: 2) . ' jam',
                PayrollComponentEnum::BPJS_KESEHATAN => Setting::resolve('bpjs_kesehatan_rate', $companyId, default: 0) . '%',
                PayrollComponentEnum::BPJS_KETENAGAKERJAAN => Setting::resolve('bpjs_ketenagakerjaan_rate', $companyId, default: 0) . '%',
                default => null,
            };
        };

        $buildRows = function (PayrollComponentTypeEnum $type) use ($applicable, $sums, $noteFor) {
            return $applicable
                ->filter(fn (PayrollComponentEnum $c) => $c->type() === $type)
                ->map(fn (PayrollComponentEnum $c) => [
                    'label' => $c->label(),
                    'note' => $noteFor($c),
                    'amount' => $sums[$c->value] ?? 0.0,
                    'hasValue' => isset($sums[$c->value]) && $sums[$c->value] > 0,
                ])
                ->values()
                ->all();
        };

        $rows = [
            'earnings' => $buildRows(PayrollComponentTypeEnum::EARNING),
            'deductions' => $buildRows(PayrollComponentTypeEnum::DEDUCTION),
        ];

        // Adjustment manual (lewat "Add Adjustment" di /admin) tidak terikat komponen manapun
        // (source_type null) — tetap ditampilkan apa adanya, bukan digabung ke baris 0 di atas.
        foreach ($items->whereNull('source_type') as $item) {
            $key = $item->type === PayrollComponentTypeEnum::EARNING->value ? 'earnings' : 'deductions';
            $rows[$key][] = ['label' => $item->name, 'note' => null, 'amount' => (float) $item->amount, 'hasValue' => true];
        }

        // Komponen yang TIDAK di-toggle aktif (mis. Fee Claim/Pajak Jasa Dokter, data-driven,
        // tidak lewat toggle company_payroll_components sama sekali) tapi ternyata beneran
        // menghasilkan uang buat payroll ini — tetap harus muncul, jangan sampai potongan/
        // pendapatan nyata hilang dari slip cuma karena tidak "terdaftar" di Komponen Gaji.
        $applicableValues = $applicable->map(fn (PayrollComponentEnum $c) => $c->value);

        foreach ($sums as $sourceType => $amount) {
            if ($amount <= 0 || $applicableValues->contains($sourceType)) {
                continue;
            }

            $enum = PayrollComponentEnum::tryFrom($sourceType);

            if (! $enum) {
                continue;
            }

            $key = $enum->type() === PayrollComponentTypeEnum::EARNING ? 'earnings' : 'deductions';
            $rows[$key][] = ['label' => $enum->label(), 'note' => $noteFor($enum), 'amount' => $amount, 'hasValue' => true];
        }

        return $rows;
    }

    /**
     * Insentif metode "import" (RSU) ditempel LANGSUNG ke payroll_items SETELAH generate
     * (lihat IncentiveImport) — Calculator-nya skip total, jadi kalau regenerate menghapus
     * payroll unpaid, PayrollItem insentif itu hilang tanpa cara otomatis untuk menulis
     * ulang (beda dari komponen lain yang selalu dihitung ulang dari sumbernya). Dipakai
     * buat block regenerate di UI SEBELUM data hilang, bukan dicegah di sini (biar caller
     * bisa kasih pesan yang jelas ke admin).
     */
    public function periodHasImportedIncentives(PayrollPeriod $period): bool
    {
        return PayrollItem::query()
            ->where('source_type', PayrollComponentEnum::INSENTIF->value)
            ->whereIn('payroll_id', Payroll::query()
                ->where('payroll_period_id', $period->id)
                ->where('is_paid', false)
                ->pluck('id'))
            ->exists();
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
