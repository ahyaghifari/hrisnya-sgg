<?php

namespace App\Services\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Enum\Payroll\PayrollComponentTypeEnum;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\UserKoperasi;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payroll\Calculators\FeeClaimCalculator;
use App\Services\Payroll\Calculators\PayrollCalculatorRegistry;
use App\Services\Payroll\Calculators\PayrollContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
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
            $this->slipFilename($payroll),
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }

    /** Format: SLIPGAJI-{bulan periode}{tahun periode}-{NIP}_{NAMA KARYAWAN}.pdf */
    private function slipFilename(Payroll $payroll): string
    {
        $period = $payroll->payrollPeriod->end_date->format('mY');

        return "SLIPGAJI-{$period}-{$payroll->user->nip}_" . strtoupper($payroll->user->name) . '.pdf';
    }

    /**
     * Password buka PDF slip gaji terenkripsi: 2 digit tanggal lahir + 3 digit terakhir NIP.
     * Null kalau salah satu datanya belum diisi — pemanggil wajib cek sebelum kirim email.
     */
    public function slipPassword(User $user): ?string
    {
        $dob = $user->profile?->date_of_birth;

        if (! $dob || ! $user->nip) {
            return null;
        }

        $day = str_pad((string) \Carbon\Carbon::parse($dob)->day, 2, '0', STR_PAD_LEFT);
        $nipLast3 = str_pad(substr($user->nip, -3), 3, '0', STR_PAD_LEFT);

        return $day . $nipLast3;
    }

    /**
     * PDF slip gaji dengan password buka (encryption bawaan CPDF backend dompdf, lihat
     * PDF::setEncryption() di barryvdh/laravel-dompdf) — dipakai lampiran email, BUKAN
     * download()/preview() biasa yang tetap tanpa password.
     *
     * @return array{binary: string, password: string, filename: string}
     */
    public function encryptedSlip(Payroll $payroll): array
    {
        $password = $this->slipPassword($payroll->user);

        if (! $password) {
            throw new \RuntimeException('Karyawan ini belum punya tanggal lahir atau NIP lengkap — tidak bisa membuat slip gaji terenkripsi.');
        }

        $pdf = Pdf::loadView('pdf.payroll.payroll', [
            'payroll' => $payroll,
            'rows' => $this->componentRows($payroll),
        ]);
        $pdf->setEncryption($password);

        return [
            'binary' => $pdf->output(),
            'password' => $password,
            'filename' => $this->slipFilename($payroll),
        ];
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

        return $pdf->stream($this->slipFilename($payroll));
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

        $quantities = $items->whereNotNull('source_type')
            ->groupBy('source_type')
            ->map(fn ($group) => (int) $group->sum('quantity'));

        // Aturan yang lagi berlaku ditempel LANGSUNG ke label (bukan lagi anotasi terpisah) —
        // diminta eksplisit buat Lembur, BPJS, & toleransi menit Keterlambatan.
        $labelFor = function (PayrollComponentEnum $c) use ($companyId): string {
            return match ($c) {
                PayrollComponentEnum::LEMBUR => $c->label() . ' >' . (int) Setting::resolve('lembur_minimum_jam', $companyId, default: 2) . ' Jam',
                PayrollComponentEnum::BPJS_KESEHATAN => $c->label() . ' ' . Setting::resolve('bpjs_kesehatan_rate', $companyId, default: 0) . '%',
                PayrollComponentEnum::BPJS_KETENAGAKERJAAN => $c->label() . ' ' . Setting::resolve('bpjs_ketenagakerjaan_rate', $companyId, default: 0) . '%',
                PayrollComponentEnum::KETERLAMBATAN => $c->label() . ' >' . (int) Setting::resolve('keterlambatan_threshold_menit', $companyId, default: 0) . ' Menit',
                default => $c->label(),
            };
        };

        // Jumlah kejadian ("NxBulan"/"NxHari") ditampilkan terpisah di sisi kanan (sejajar
        // nominal), bukan nempel di label — dibaca dari quantity PayrollItem yang sudah
        // dihitung Calculator, bukan dihitung ulang di sini.
        $noteFor = function (PayrollComponentEnum $c) use ($quantities): ?string {
            return match ($c) {
                PayrollComponentEnum::KETERLAMBATAN,
                PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT => ($quantities[$c->value] ?? 0) . 'x/Bulan',
                PayrollComponentEnum::CUTI,
                PayrollComponentEnum::IZIN_SEHARIAN => ($quantities[$c->value] ?? 0) . 'x/Hari',
                default => null,
            };
        };

        $buildRows = function (PayrollComponentTypeEnum $type) use ($applicable, $sums, $labelFor, $noteFor) {
            return $applicable
                ->filter(fn (PayrollComponentEnum $c) => $c->type() === $type)
                ->map(fn (PayrollComponentEnum $c) => [
                    'component' => $c->value,
                    'label' => $labelFor($c),
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

    public function regeneratePayroll(PayrollPeriod $period, ?string $generatedByUserId = null): void
    {
        DB::transaction(function () use ($period, $generatedByUserId) {
            // Delete only unpaid payrolls; paid payrolls are kept as-is.
            // payroll_items will be removed automatically via cascadeOnDelete.
            Payroll::query()
                ->where('payroll_period_id', $period->id)
                ->where('is_paid', false)
                ->delete();

            // Re-run generation — payrollAlreadyExists() will skip paid ones.
            $this->generatePayroll($period, $generatedByUserId);
        });
    }

    public function generatePayroll(PayrollPeriod $period, ?string $generatedByUserId = null): void
    {
        DB::transaction(function () use ($period, $generatedByUserId) {
            $users = User::query()
                ->where('company_id', $period->company_id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->get();

            $activeComponents = CompanyPayrollComponent::query()
                ->where('company_id', $period->company_id)
                ->where('is_active', true)
                ->get();

            $this->syncKoperasiManualLines($period, $users);

            foreach ($users as $user) {
                if ($this->payrollAlreadyExists($user, $period)) {
                    continue;
                }

                // Tipe karyawan yang tidak terdaftar sebagai penerima komponen APA PUN (belum
                // di-assign ke satu pun komponen di halaman "Penerima Komponen Gaji") tidak ikut
                // digenerate sama sekali — tidak dapat slip gaji, bukan cuma komponen kosong.
                if (! PayrollComponentRecipient::isRegisteredAnywhere($period->company_id, $user->profile?->employee_type_id)) {
                    continue;
                }

                $this->generateForUser($user, $period, $activeComponents, $generatedByUserId);
            }

            $period->update([
                'processed_at' => now(),
                'generated_by' => $generatedByUserId ?? $period->generated_by,
            ]);
        });
    }

    /**
     * Regenerate SATU payroll saja (bukan seluruh periode) — dipakai tombol "Regenerate" per
     * karyawan di halaman detail payroll. Payroll yang sudah dibayar tidak boleh disentuh.
     */
    public function regenerateSinglePayroll(Payroll $payroll, ?string $generatedByUserId = null): Payroll
    {
        if ($payroll->is_paid) {
            throw new \RuntimeException('Payroll yang sudah dibayar tidak bisa di-regenerate.');
        }

        if (! PayrollComponentRecipient::isRegisteredAnywhere($payroll->payrollPeriod->company_id, $payroll->user->profile?->employee_type_id)) {
            throw new \RuntimeException('Tipe karyawan ini belum terdaftar sebagai penerima komponen gaji apa pun — atur dulu di halaman Penerima Komponen Gaji.');
        }

        return DB::transaction(function () use ($payroll, $generatedByUserId) {
            $period = $payroll->payrollPeriod;
            $user = $payroll->user;

            $activeComponents = CompanyPayrollComponent::query()
                ->where('company_id', $period->company_id)
                ->where('is_active', true)
                ->get();

            $this->syncKoperasiManualLines($period, collect([$user]));

            $payroll->delete(); // payroll_items ikut terhapus lewat cascadeOnDelete

            $newPayroll = $this->generateForUser($user, $period, $activeComponents, $generatedByUserId);

            $period->update([
                'generated_by' => $generatedByUserId ?? $period->generated_by,
            ]);

            return $newPayroll;
        });
    }

    private function generateForUser(User $user, PayrollPeriod $period, Collection $activeComponents, ?string $generatedByUserId = null): Payroll
    {
        $payroll = Payroll::create([
            'user_id' => $user->id,
            'payroll_period_id' => $period->id,
            'generated_by' => $generatedByUserId,
            'generated_at' => now(),
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

        return $payroll;
    }

    /**
     * Koperasi anggota aktif otomatis "ditulis ulang" jadi payroll_manual_lines tiap Generate/
     * Regenerate — finance tidak perlu input manual tiap periode lagi (cuma pas amount/status
     * anggota berubah, lihat halaman Koperasi). KoperasiCalculator tetap baca dari
     * payroll_manual_lines seperti biasa, tidak berubah.
     */
    private function syncKoperasiManualLines(PayrollPeriod $period, Collection $users): void
    {
        $koperasiRows = UserKoperasi::query()
            ->where('is_active', true)
            ->whereIn('user_id', $users->pluck('id'))
            ->get();

        foreach ($koperasiRows as $row) {
            PayrollManualLine::updateOrCreate(
                [
                    'payroll_period_id' => $period->id,
                    'user_id' => $row->user_id,
                    'component' => PayrollComponentEnum::KOPERASI->value,
                ],
                [
                    'amount' => $row->amount,
                    'note' => 'Auto-sync dari Koperasi',
                ]
            );
        }
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
        // Halaman "Penerima Komponen Gaji" — TIDAK ADA baris terdaftar utk kombinasi
        // company+component ini = TIDAK ADA yang eligible (default ketat, disengaja — lihat
        // App\Models\Payroll\PayrollComponentRecipient).
        if (! PayrollComponentRecipient::isEligible($companyComponent->company_id, $companyComponent->component, $user->profile?->employee_type_id)) {
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
