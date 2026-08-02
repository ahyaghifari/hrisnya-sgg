<?php

namespace App\Services\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Enum\Payroll\PayrollComponentTypeEnum;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollManualLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\UserKoperasi;
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
     * @return array{earnings: array<int, array{component: ?string, label: string, note: ?string, amount: float, hasValue: bool}>, deductions: array<int, array{component: ?string, label: string, note: ?string, amount: float, hasValue: bool}>}
     *
     * Load langsung dari payroll_items — tidak lagi cek company_payroll_components/toggle
     * aktif sama sekali (2026-08-02). Sejak runComponent() menjamin tiap komponen aktif+eligible
     * SELALU punya baris (Rp0 kalau perlu, lihat PayrollComponentEnum::defaultRowName()), baris
     * yang tersimpan itu SENDIRI sudah lengkap & akurat secara historis — tidak perlu direkonstruksi
     * ulang dari kondisi toggle/setting SAAT INI. Efek samping yang disengaja: payroll lama tetap
     * tampil apa adanya walau company belakangan mematikan toggle komponennya (integritas historis).
     */
    public function componentRows(Payroll $payroll): array
    {
        // Urutan tampil ikut urutan deklarasi PayrollComponentEnum (urutan yang sama dipakai di
        // halaman Komponen Gaji) - BUKAN urutan baris payroll_items di DB (itu ikut urutan
        // Calculator jalan/CompanyPayrollComponent, bisa beda-beda, jangan sampai keliatan kacau/
        // alfabetis). Item tanpa source_type (adjustment manual) ditaruh paling akhir.
        $order = collect(PayrollComponentEnum::cases())->map(fn ($c) => $c->value)->flip();

        $items = $payroll->items->sortBy(
            fn ($item) => $item->source_type ? ($order[$item->source_type] ?? PHP_INT_MAX) : PHP_INT_MAX
        );

        // Komponen yang note-nya tetap ditampilkan di slip (keterangan jumlah kejadian, mis.
        // "2x/Bulan"/"1x/Hari") - komponen lain notenya disembunyikan di tampilan slip walau
        // tetap tersimpan utuh di payroll_items.
        $notedComponents = [
            PayrollComponentEnum::KETERLAMBATAN->value,
            PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT->value,
            PayrollComponentEnum::IZIN_SEHARIAN->value,
            PayrollComponentEnum::CUTI->value,
        ];

        $rows = ['earnings' => [], 'deductions' => []];

        foreach ($items as $item) {
            $key = $item->type === PayrollComponentTypeEnum::EARNING->value ? 'earnings' : 'deductions';

            $rows[$key][] = [
                'component' => $item->source_type,
                'label' => $item->name,
                'note' => in_array($item->source_type, $notedComponents, true) ? $item->note : null,
                'amount' => (float) $item->amount,
                'hasValue' => (float) $item->amount > 0,
            ];
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

            // Re-run generation — generatePayroll() sendiri juga sudah skip yang paid & hapus-
            // ulang yang belum dibayar, jadi baris di atas sebenarnya redundan tapi tidak masalah.
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
                // Payroll yang SUDAH DIBAYAR dikunci total, tidak boleh disentuh lagi. Payroll
                // yang BELUM dibayar dihapus & digenerate ulang dari nol (bukan di-skip) - kalau
                // di-skip, klik "Generate" di halaman Periode Gaji ini tidak akan pernah
                // menerapkan perbaikan Calculator (mis. fix prorate Gaji Pokok/window Izin) ke
                // payroll yang kebetulan sudah ada baris lamanya, cuma "Regenerate" (yang
                // eksplisit hapus dulu) yang kena - membingungkan, disamakan di sini (2026-08-02).
                $existing = Payroll::query()
                    ->where('user_id', $user->id)
                    ->where('payroll_period_id', $period->id)
                    ->first();

                if ($existing) {
                    if ($existing->is_paid) {
                        continue;
                    }

                    $existing->delete(); // payroll_items ikut terhapus lewat cascadeOnDelete
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
            $key = [
                'payroll_period_id' => $period->id,
                'user_id' => $row->user_id,
                'component' => PayrollComponentEnum::KOPERASI->value,
            ];

            // Delete-then-create, bukan updateOrCreate. Koperasi selalu 1 nilai (bukan rincian
            // kayak Bon mode Detail) - kalau somehow ada >1 baris nyasar (constraint unique sudah
            // dilonggarkan, lihat migration 2026_08_01_160000_*), updateOrCreate cuma nimpa baris
            // PERTAMA yang match, sisanya tetap ada dan ikut dijumlah KoperasiCalculator (loop
            // semua baris) - dobel hitung senyap. Delete dulu memastikan selalu tepat 1 baris.
            PayrollManualLine::where($key)->delete();
            PayrollManualLine::create([
                ...$key,
                'amount' => $row->amount,
                'note' => 'Auto-sync dari Koperasi',
            ]);
        }
    }


    private function runComponent(
        CompanyPayrollComponent $companyComponent,
        User $user,
        Payroll $payroll,
        PayrollPeriod $period,
        PayrollContext $context
    ): void {
        $component = PayrollComponentEnum::tryFrom($companyComponent->component);

        if (! $component) {
            return;
        }

        // Halaman "Penerima Komponen Gaji" — TIDAK ADA baris terdaftar utk kombinasi
        // company+component ini = TIDAK eligible MENERIMA (default ketat, disengaja — lihat
        // App\Models\Payroll\PayrollComponentRecipient). Ini cuma nge-gate perhitungan/pembayaran
        // (Calculator tidak dipanggil sama sekali), BUKAN keberadaan barisnya — komponen yang
        // aktif di company ini tetap harus tampil sebagai baris Rp0 di slip meski tipe karyawan
        // ini belum terdaftar sebagai penerima (2026-08-02, per keputusan user: "penerima cuma
        // nentuin dia dapat uang atau tidak, bukan nentuin barisnya ada atau tidak").
        $isEligible = PayrollComponentRecipient::isEligible($companyComponent->company_id, $companyComponent->component, $user->profile?->employee_type_id);

        if ($isEligible) {
            $calculatorClass = PayrollCalculatorRegistry::resolve($component);

            if ($calculatorClass) {
                app($calculatorClass)->calculate($user, $payroll, $period, $context);
            }
        }

        // Komponen ini aktif tapi belum ada barisnya sama sekali — baik karena tipe karyawan ini
        // bukan penerima, atau eligible tapi Calculator-nya tidak menghasilkan apa-apa (nol/data
        // belum lengkap). Tetap harus KELIHATAN di slip sebagai Rp0, bukan hilang total. Fee
        // Claim/Pajak Jasa Dokter TIDAK lewat method ini (alwaysOn, lihat generateForUser()),
        // jadi otomatis tidak kena aturan ini.
        if (! $payroll->items()->where('source_type', $component->value)->exists()) {
            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'name' => $component->defaultRowName($companyComponent->company_id),
                'type' => $component->type()->value,
                'quantity' => 0,
                'amount' => 0,
                'note' => $component->defaultRowNote(),
                'source_type' => $component->value,
            ]);
        }
    }
}
