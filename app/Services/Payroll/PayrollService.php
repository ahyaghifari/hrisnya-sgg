<?php

namespace App\Services\Payroll;

use App\Enum\Payroll\ExecutiveAutoComponentEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Enum\Payroll\PayrollComponentTypeEnum;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollComponentOverride;
use App\Models\Payroll\PayrollComponentRecipient;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Employee\Employee;
use App\Models\User;
use App\Services\Payroll\Calculators\ExecutiveAllowanceCalculator;
use App\Services\Payroll\Calculators\PayrollCalculatorRegistry;
use App\Services\Payroll\Calculators\PayrollContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Orchestrator tipis — logika perhitungan tiap komponen ada di
 * app/Services/Payroll/Calculators/*.php (Registry+Strategy+Config, lihat
 * planning/2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md). Method lama
 * handleXxxComponent() sudah dipindah semua ke Calculator masing-masing.
 */
class PayrollService
{
    /**
     * PayrollPeriod punya global scope BelongsToCompany. Di request web milik user
     * LIHAT_PERUSAHAAN_LAIN yang buka slip payroll company lain (mis. hasil import
     * manual), lazy-load $payroll->payrollPeriod jadi null & blade slip crash
     * (baca ->end_date). Di queue job scope-nya sudah di-skip (console), jadi ini
     * cuma perlu di jalur web: download()/preview()/encryptedSlip().
     */
    private function ensurePeriodLoaded(Payroll $payroll): void
    {
        if (! $payroll->relationLoaded('payrollPeriod') || $payroll->payrollPeriod === null) {
            $payroll->load([
                'payrollPeriod' => fn ($q) => $q->withoutGlobalScope('company')->with('company'),
            ]);
        }
    }

    public function download(Payroll $payroll): StreamedResponse
    {
        $this->ensurePeriodLoaded($payroll);

        $pdf = Pdf::loadView('pdf.payroll.payroll', [
            'payroll' => $payroll,
            'rows' => $this->componentRows($payroll, compact: true),
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
        $this->ensurePeriodLoaded($payroll);

        $password = $this->slipPassword($payroll->user);

        if (! $password) {
            throw new \RuntimeException('Karyawan ini belum punya tanggal lahir atau NIP lengkap — tidak bisa membuat slip gaji terenkripsi.');
        }

        $pdf = Pdf::loadView('pdf.payroll.payroll', [
            'payroll' => $payroll,
            'rows' => $this->componentRows($payroll, compact: true),
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
        $this->ensurePeriodLoaded($payroll);

        $pdf = Pdf::loadView('pdf.payroll.payroll', [
            'payroll' => $payroll,
            'rows' => $this->componentRows($payroll, compact: true),
        ]);

        return $pdf->stream($this->slipFilename($payroll));
    }

    /** Path relatif (disk `local`, di luar webroot) file ZIP slip gaji satu periode. */
    public function slipZipRelPath(string $periodId): string
    {
        return "payroll-slip-zips/{$periodId}.zip";
    }

    private function slipZipFilename(PayrollPeriod $period): string
    {
        return 'slip-gaji-' . str(($period->company?->name ?? 'perusahaan') . '-' . $period->end_date->translatedFormat('F-Y'))->slug() . '.zip';
    }

    /**
     * Bangun ZIP berisi PDF slip gaji untuk semua payroll SUDAH DIBAYAR di satu periode,
     * simpan ke disk `local`. PDF di dalam ZIP TANPA password/enkripsi (beda dari lampiran
     * email yang pakai encryptedSlip()) — ZIP dianggap sudah dibagikan lewat kanal internal
     * yang aman. Berat (ratusan PDF dompdf) — dijalankan lewat queue (BuildPayrollSlipZipJob),
     * BUKAN request web. Data payroll is_paid sudah beku (regenerateSinglePayroll() menolak
     * yang is_paid) jadi hasilnya deterministik & aman di-cache sebagai file. Payroll yang
     * gagal dirender dilewati — nama-namanya ditulis ke `_DILEWATI.txt` di dalam ZIP.
     */
    public function buildSlipZipToStorage(PayrollPeriod $period): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('payroll-slip-zips');

        $finalAbs = $disk->path($this->slipZipRelPath($period->id));
        $buildAbs = $finalAbs . '.building';
        $stageDir = $finalAbs . '.stage'; // folder PDF mentah sementara (dibersihkan di akhir)

        // Sisa build yang gagal di tengah jalan (worker mati) — sapu dulu.
        File::deleteDirectory($stageDir);
        File::makeDirectory($stageDir, 0755, true);

        // Semua isi dibungkus 1 folder root (= nama file tanpa .zip) supaya pas di-extract
        // jadi satu folder rapi, bukan file/folder divisi berhamburan di direktori tujuan.
        $root = preg_replace('/\.zip$/', '', $this->slipZipFilename($period)) ?: 'slip-gaji';
        $skipped = [];
        $entries = []; // [nama di dalam zip => path file mentah di disk], urut

        // ── Fase 1: render tiap PDF LANGSUNG ke file di disk, JANGAN ditahan di memory.
        // memory_limit 128M tidak muat kalau ratusan output dompdf numpuk di buffer
        // ZipArchive::addFromString() sampai close() ("Allowed memory size exhausted").
        // Tulis ke disk, unset, gc — pemakaian memory jadi rata berapa pun jumlah slip.
        Payroll::where('payroll_period_id', $period->id)
            ->where('is_paid', true)
            ->with(['user.profile', 'payrollPeriod.company'])
            ->chunkById(50, function ($payrolls) use ($stageDir, $root, &$skipped, &$entries) {
                foreach ($payrolls as $payroll) {
                    $name = $root . '/' . $this->slipFilename($payroll);

                    try {
                        // TANPA setEncryption() — PDF di ZIP tidak di-password (beda dari encryptedSlip()).
                        $pdf = Pdf::loadView('pdf.payroll.payroll', [
                            'payroll' => $payroll,
                            'rows' => $this->componentRows($payroll, compact: true),
                        ]);
                        $abs = $stageDir . '/' . md5($name) . '.pdf';
                        if (file_put_contents($abs, $pdf->output()) === false) {
                            throw new \RuntimeException("gagal tulis {$abs}");
                        }
                        $entries[$name] = $abs;
                    } catch (\Throwable) {
                        $skipped[] = trim(($payroll->user?->nip ?? '?') . ' — ' . ($payroll->user?->name ?? '?'));
                    } finally {
                        unset($pdf);
                    }
                }

                gc_collect_cycles();
            });

        // ── Fase 2: bungkus jadi 1 ZIP. addFile() cuma catat path — isinya di-stream dari
        // disk saat close(), tidak di-load ke memory PHP, jadi aman walau ribuan slip.
        $zip = new \ZipArchive;
        $zip->open($buildAbs, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($entries as $name => $abs) {
            $zip->addFile($abs, $name);
        }

        if ($skipped !== []) {
            $zip->addFromString(
                $root . '/_DILEWATI.txt',
                "Slip gaji berikut gagal dibuat (data tidak lengkap / error render):\n\n" . implode("\n", $skipped),
            );
        }

        $zip->close();

        // Rename atomik di akhir — file `.zip` final tidak pernah ada dalam kondisi separuh jadi.
        @rename($buildAbs, $finalAbs);
        File::deleteDirectory($stageDir);
    }

    public function downloadStoredSlipZip(PayrollPeriod $period): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return response()->download(
            Storage::disk('local')->path($this->slipZipRelPath($period->id)),
            $this->slipZipFilename($period),
        );
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
    public function componentRows(Payroll $payroll, bool $compact = false): array
    {
        // Urutan tampil ikut urutan deklarasi PayrollComponentEnum (urutan yang sama dipakai di
        // halaman Komponen Gaji) - BUKAN urutan baris payroll_items di DB (itu ikut urutan
        // Calculator jalan/CompanyPayrollComponent, bisa beda-beda, jangan sampai keliatan kacau/
        // alfabetis). Item tanpa source_type (adjustment manual) ditaruh paling akhir.
        $order = collect(PayrollComponentEnum::cases())->map(fn ($c) => $c->value)->flip();

        // source_type kustom dari import Excel manual (ManualPayrollExcelImporter) — bukan case
        // enum, jadi kalau tidak diselipkan di sini bakal numpuk di paling akhir. Pakai key
        // pecahan supaya nyempil di posisi yang logis di antara komponen enum.
        $customOrder = [
            'tunjangan_khusus' => ($order['insentif'] ?? 0) + 0.5,   // setelah Insentif
            'izin_cuti_gabung' => ($order['izin_seharian'] ?? 0) + 0.5, // di slot Izin/Cuti
            'potongan_khusus' => ($order['bon'] ?? 0) + 0.5,          // setelah Bon
        ];

        $items = $payroll->items->sortBy(function ($item) use ($order, $customOrder) {
            if (! $item->source_type) {
                return PHP_INT_MAX;
            }

            return $order[$item->source_type] ?? $customOrder[$item->source_type] ?? PHP_INT_MAX;
        });

        // Komponen yang note-nya tetap ditampilkan di slip (keterangan jumlah kejadian, mis.
        // "2x/Bulan"/"1x/Hari") - komponen lain notenya disembunyikan di tampilan slip walau
        // tetap tersimpan utuh di payroll_items.
        $notedComponents = [
            PayrollComponentEnum::KETERLAMBATAN->value,
            PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT->value,
            PayrollComponentEnum::IZIN_SEHARIAN->value,
            PayrollComponentEnum::CUTI->value,
            // Komponen kustom dari import Excel manual (ManualPayrollExcelImporter).
            'izin_cuti_gabung',
        ];

        $rows = ['earnings' => [], 'deductions' => []];

        foreach ($items as $item) {
            $key = $item->type === PayrollComponentTypeEnum::EARNING->value ? 'earnings' : 'deductions';

            $note = in_array($item->source_type, $notedComponents, true) ? $item->note : null;

            // Keterlambatan simpan note panjang (breakdown telat/kompensasi/denda) buat halaman
            // detail - di slip PDF disingkat jadi "Nx/Bulan" senada komponen lain (2026-08-12).
            if ($compact && $note && $item->source_type === PayrollComponentEnum::KETERLAMBATAN->value) {
                // quantity kolom desimal -> "4.00", buang angka nol di belakang biar "4x/Bulan".
                $note = ((float) $item->quantity + 0) . 'x/Bulan';
            }

            $rows[$key][] = [
                'component' => $item->source_type,
                'label' => $item->name,
                'note' => $note,
                'amount' => (float) $item->amount,
                'hasValue' => (float) $item->amount > 0,
            ];
        }

        return $rows;
    }

    public function regeneratePayroll(PayrollPeriod $period, ?string $generatedByUserId = null): void
    {
        DB::transaction(function () use ($period, $generatedByUserId) {
            // Snapshot nilai LAMA dulu (sebelum dihapus) - dipakai bandingkan ke hasil baru
            // di logRegenerateChanges(), supaya perubahan angka akibat Regenerate (bukan cuma
            // penyesuaian manual) ikut punya jejak di audit_logs.
            $before = $this->snapshotPayrollTotals($period);

            // TIDAK bulk-delete payroll di sini lagi (dulu ada, dihapus 2026-08-22) -
            // generatePayroll() sendiri sudah skip yang paid & hapus-ulang yang belum dibayar
            // PER USER lewat capturePreservedItems() dulu baru delete (lihat loopnya). Bulk
            // delete duluan di sini bikin capturePreservedItems() selalu nemu payroll kosong
            // (baris edited_manually ikut lenyap tanpa sempat dibawa pindah) - regenerate lewat
            // Periode Gaji jadi beda perilaku dari regenerate satu-satu padahal harusnya sama.
            $this->generatePayroll($period, $generatedByUserId);

            $this->logRegenerateChanges($period, $before);
        });
    }

    /** @return Collection<string, array{gross_salary: float, total_deductions: float, net_salary: float}> keyed by employee_id */
    private function snapshotPayrollTotals(PayrollPeriod $period): Collection
    {
        return Payroll::query()
            ->where('payroll_period_id', $period->id)
            ->where('is_paid', false)
            ->get()
            ->keyBy('employee_id')
            ->map(fn (Payroll $p) => [
                'gross_salary' => (float) $p->gross_salary,
                'total_deductions' => (float) $p->total_deductions,
                'net_salary' => (float) $p->net_salary,
            ]);
    }

    /**
     * Bandingkan snapshot SEBELUM Regenerate dengan hasil SESUDAHNYA, 1 baris AuditLog
     * (module `payroll_regenerate`) per karyawan yang totalnya benar-benar berubah - karyawan
     * yang angkanya tetap sama TIDAK dicatat (regenerate tanpa perubahan nyata bukan kejadian
     * yang perlu jejak, sama filosofinya dengan kenapa Calculator sendiri tidak pakai
     * LogsAuditChanges, lihat docblock PayrollItemsRelationManager). Snapshot per-KOMPONEN
     * (payroll_items) sengaja tidak diambil - terlalu detail/berisik untuk regenerate massal;
     * total gross/potongan/bersih sudah cukup buat tahu "ada yang berubah, di payroll siapa,
     * kapan" - penelusuran detail komponen mana yang berubah tetap bisa dari PayrollItem
     * saat ini dibanding logika Calculator-nya.
     *
     * @param Collection<string, array{gross_salary: float, total_deductions: float, net_salary: float}> $before
     */
    private function logRegenerateChanges(PayrollPeriod $period, Collection $before): void
    {
        if ($before->isEmpty()) {
            return;
        }

        $after = Payroll::query()
            ->where('payroll_period_id', $period->id)
            ->whereIn('employee_id', $before->keys())
            ->with('user')
            ->get();

        foreach ($after as $payroll) {
            $old = $before->get($payroll->employee_id);
            if ($old === null) {
                continue;
            }

            $this->logRegenerateChangeIfAny($period, $payroll, $old);
        }
    }

    /** @param array{gross_salary: float, total_deductions: float, net_salary: float} $old */
    private function logRegenerateChangeIfAny(PayrollPeriod $period, Payroll $payroll, array $old): void
    {
        $new = [
            'gross_salary' => (float) $payroll->gross_salary,
            'total_deductions' => (float) $payroll->total_deductions,
            'net_salary' => (float) $payroll->net_salary,
        ];

        $unchanged = abs($old['gross_salary'] - $new['gross_salary']) < 0.5
            && abs($old['total_deductions'] - $new['total_deductions']) < 0.5
            && abs($old['net_salary'] - $new['net_salary']) < 0.5;

        if ($unchanged) {
            return;
        }

        $describe = fn (array $v) => 'Gross Rp' . number_format($v['gross_salary'], 0, ',', '.')
            . ', Potongan Rp' . number_format($v['total_deductions'], 0, ',', '.')
            . ', Bersih Rp' . number_format($v['net_salary'], 0, ',', '.');

        \App\Models\AuditLog::record(
            'payroll_regenerate',
            $period->company_id,
            'payroll:' . $payroll->id,
            $payroll->user?->name . ' — ' . $period->start_date?->format('M Y') . ' (regenerate)',
            $describe($old),
            $describe($new),
        );
    }

    public function generatePayroll(PayrollPeriod $period, ?string $generatedByUserId = null): void
    {
        DB::transaction(function () use ($period, $generatedByUserId) {
            // Unit generate = karyawan (employees), bukan akun. status 'aktif' = users.is_active &&
            // belum soft delete (disinkron EmployeeSyncService). Dua akun Super Admin ikut punya baris
            // employees tapi bukan penerima gaji; Presiden Direktur & Sekretaris Direktur sengaja
            // disertakan (instruksi user 2026-08-11, lihat User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS).
            // Karyawan tanpa akun (user_id kosong) belum didukung: payrolls.user_id masih NOT NULL.
            $employees = Employee::query()
                ->with('user')
                ->where('company_id', $period->company_id)
                ->where('status', 'aktif')
                ->whereNotNull('user_id')
                ->whereNotIn('user_id', array_diff(User::NON_KARYAWAN_IDS, User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS))
                ->get();

            $activeComponents = CompanyPayrollComponent::query()
                ->where('company_id', $period->company_id)
                ->where('is_active', true)
                ->get();

            foreach ($employees as $employee) {
                $user = $employee->user;
                // Payroll yang SUDAH DIBAYAR dikunci total, tidak boleh disentuh lagi. Payroll
                // yang BELUM dibayar dihapus & digenerate ulang dari nol (bukan di-skip) - kalau
                // di-skip, klik "Generate" di halaman Periode Gaji ini tidak akan pernah
                // menerapkan perbaikan Calculator (mis. fix prorate Gaji Pokok/window Izin) ke
                // payroll yang kebetulan sudah ada baris lamanya, cuma "Regenerate" (yang
                // eksplisit hapus dulu) yang kena - membingungkan, disamakan di sini (2026-08-02).
                $existing = Payroll::query()
                    ->where('employee_id', $employee->id)
                    ->where('payroll_period_id', $period->id)
                    ->first();

                $preservedItems = [];

                if ($existing) {
                    if ($existing->is_paid) {
                        continue;
                    }

                    $preservedItems = $this->capturePreservedItems($existing);
                    $existing->delete(); // payroll_items ikut terhapus lewat cascadeOnDelete
                }

                // Tipe karyawan yang tidak terdaftar sebagai penerima komponen APA PUN (belum
                // di-assign ke satu pun komponen di halaman "Penerima Komponen Gaji") tidak ikut
                // digenerate sama sekali — tidak dapat slip gaji, bukan cuma komponen kosong.
                // Eksekutif PAYROLL_ELIGIBLE_EXECUTIVE_IDS DIKECUALIKAN dari gerbang ini
                // (2026-08-12, direvisi) - mereka SENGAJA tidak lewat komponen otomatis apa pun
                // (lihat generateForEmployee()), semua pendapatan/potongan diinput manual lewat
                // halaman Data Eksekutif (ExecutiveAllowanceCalculator) - gerbang recipient
                // employee-type ini tidak relevan buat mereka, tapi baris Payroll-nya sendiri
                // tetap harus ada supaya ada tempat nempel input manual itu.
                $isEligibleExecutive = in_array($user->id, User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS, true);

                if (! $isEligibleExecutive && ! PayrollComponentRecipient::isRegisteredAnywhere($period->company_id, $employee->employee_type_id)) {
                    continue;
                }

                $this->generateForEmployee($employee, $period, $activeComponents, $generatedByUserId, $preservedItems);
            }

            $period->update([
                'processed_at' => now(),
                'generated_by' => $generatedByUserId ?? $period->generated_by,
            ]);
        });
    }

    /**
     * Buat SATU payroll manual di luar alur Generate periode — dipakai tombol "Buat Payroll" di
     * halaman daftar Payroll. Reuse pipeline Calculator yang sama (generateForEmployee()), bukan
     * Payroll::create() polos, supaya payroll_items tetap lengkap & konsisten dengan hasil
     * Generate biasa.
     */
    public function generateSingleForEmployee(Employee $employee, PayrollPeriod $period, ?string $generatedByUserId = null): Payroll
    {
        $user = $employee->user;

        if (Payroll::where('employee_id', $employee->id)->where('payroll_period_id', $period->id)->exists()) {
            throw new \RuntimeException('Karyawan ini sudah punya payroll di periode ini.');
        }

        // Eksekutif PAYROLL_ELIGIBLE_EXECUTIVE_IDS dikecualikan dari gerbang ini juga (lihat
        // generateForEmployee()) - sama alasan seperti di generatePayroll().
        if (! in_array($user->id, User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS, true)
            && ! PayrollComponentRecipient::isRegisteredAnywhere($period->company_id, $employee->employee_type_id)) {
            throw new \RuntimeException('Tipe karyawan ini belum terdaftar sebagai penerima komponen gaji apa pun — atur dulu di halaman Penerima Komponen Gaji.');
        }

        return DB::transaction(function () use ($employee, $period, $generatedByUserId) {
            $activeComponents = CompanyPayrollComponent::query()
                ->where('company_id', $period->company_id)
                ->where('is_active', true)
                ->get();

            return $this->generateForEmployee($employee, $period, $activeComponents, $generatedByUserId);
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

        // Eksekutif PAYROLL_ELIGIBLE_EXECUTIVE_IDS dikecualikan dari gerbang ini juga (lihat
        // generateForEmployee()) - sama alasan seperti di generatePayroll().
        if (! in_array($payroll->user_id, User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS, true)
            && ! PayrollComponentRecipient::isRegisteredAnywhere($payroll->payrollPeriod->company_id, $payroll->employee->employee_type_id)) {
            throw new \RuntimeException('Tipe karyawan ini belum terdaftar sebagai penerima komponen gaji apa pun — atur dulu di halaman Penerima Komponen Gaji.');
        }

        return DB::transaction(function () use ($payroll, $generatedByUserId) {
            $period = $payroll->payrollPeriod;
            $employee = $payroll->employee;

            $old = [
                'gross_salary' => (float) $payroll->gross_salary,
                'total_deductions' => (float) $payroll->total_deductions,
                'net_salary' => (float) $payroll->net_salary,
            ];

            $activeComponents = CompanyPayrollComponent::query()
                ->where('company_id', $period->company_id)
                ->where('is_active', true)
                ->get();

            $preservedItems = $this->capturePreservedItems($payroll);

            $payroll->delete(); // payroll_items ikut terhapus lewat cascadeOnDelete

            $newPayroll = $this->generateForEmployee($employee, $period, $activeComponents, $generatedByUserId, $preservedItems);

            $period->update([
                'generated_by' => $generatedByUserId ?? $period->generated_by,
            ]);

            $this->logRegenerateChangeIfAny($period, $newPayroll->load('user'), $old);

            return $newPayroll;
        });
    }

    /**
     * Terapkan 1 revisi manual (dari Import Revisi Payroll, lihat PayrollRevisionImport) ke
     * payroll_items + total payroll. Read-modify-write PHP, BUKAN increment()/decrement() SQL
     * mentah — kolom nominal payroll di-cast PayrollEncrypted (ciphertext, tidak bisa dijumlah
     * di level DB), pola sama dengan PayrollItemsRelationManager::applyToPayrollTotals() di
     * admin panel (cuma dipanggil bulk lewat import, bukan 1-per-1 lewat form Filament).
     *
     * SENGAJA menulis langsung ke PayrollItem yang sudah ada (bukan regenerate) - baris ini
     * ditandai edited_manually=true, jadi kalau Generate/Regenerate jalan belakangan
     * (mis. tombol "Regenerate Payroll Ini"), Calculator komponen ini di-skip dan nilai manual
     * ini dibawa pindah apa adanya, bukan ketimpa balik oleh hasil hitungan otomatis (lihat
     * PayrollContext::protectedSourceTypes).
     */
    public function applyManualRevision(Payroll $payroll, PayrollComponentEnum $component, float $newAmount, string $note, ?string $updatedByUserId = null): void
    {
        if ($payroll->is_paid) {
            throw new \RuntimeException('Payroll yang sudah dibayar tidak bisa direvisi.');
        }

        DB::transaction(function () use ($payroll, $component, $newAmount, $note) {
            $item = $payroll->items()->where('source_type', $component->value)->first();
            $oldAmount = (float) ($item->amount ?? 0);
            $type = $component->type()->value;

            if ($item) {
                $item->amount = $newAmount;
                $item->note = $note;
                $item->edited_manually = true;
                $item->save();
            } else {
                $item = PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'name' => $component->label(),
                    'type' => $type,
                    'quantity' => 1,
                    'amount' => $newAmount,
                    'note' => $note,
                    'source_type' => $component->value,
                    'edited_manually' => true,
                ]);
            }

            $delta = $newAmount - $oldAmount;
            if ($type === PayrollComponentTypeEnum::EARNING->value) {
                $payroll->gross_salary += $delta;
                $payroll->net_salary += $delta;
            } else {
                $payroll->total_deductions += $delta;
                $payroll->net_salary -= $delta;
            }
            $payroll->save();

            \App\Models\AuditLog::record(
                'payroll_adjustment',
                $payroll->payrollPeriod?->company_id,
                'payroll_item:' . $item->id,
                $component->label() . ' — ' . $payroll->user?->name . ' (' . $note . ')',
                (string) $oldAmount,
                (string) $newAmount,
            );
        });
    }

    /**
     * @param array<int, array<string, mixed>> $preservedItems Snapshot baris edited_manually=true
     *   dari payroll LAMA user ini (lihat capturePreservedItems()) - dibawa pindah apa adanya ke
     *   payroll baru, source_type-nya ditandai protected di $context supaya Calculator terkait
     *   di-skip (bukan menimpa balik).
     */
    private function generateForEmployee(Employee $employee, PayrollPeriod $period, Collection $activeComponents, ?string $generatedByUserId = null, array $preservedItems = []): Payroll
    {
        $user = $employee->user; // Calculator masih menerima akun (User)

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'user_id' => $employee->user_id, // kolom lama, masih NOT NULL
            'payroll_period_id' => $period->id,
            'generated_by' => $generatedByUserId,
            'generated_at' => now(),
        ]);

        $context = new PayrollContext();

        foreach ($preservedItems as $itemData) {
            PayrollItem::create([
                ...$itemData,
                'payroll_id' => $payroll->id,
                'edited_manually' => true,
            ]);

            if ($itemData['type'] === PayrollComponentTypeEnum::EARNING->value) {
                $context->addEarning((float) $itemData['amount']);
            } else {
                $context->addDeduction((float) $itemData['amount']);
            }

            if ($itemData['source_type']) {
                $context->protectedSourceTypes[] = $itemData['source_type'];
            }
        }

        // Eksekutif PAYROLL_ELIGIBLE_EXECUTIVE_IDS SENGAJA tidak lewat komponen otomatis apa
        // pun (Gaji Pokok/BPJS/Keterlambatan/Fee Claim/dst) - dari pendapatan sampai potongan
        // semuanya diinput manual lewat halaman Data Eksekutif (2026-08-12, instruksi user).
        // Cuma ExecutiveAllowanceCalculator yang jalan buat mereka - KECUALI komponen yang
        // masuk ExecutiveAutoComponentEnum (2026-08-20, instruksi user: "misal Tunjangan Masa
        // Kerja ngikut yang ada, ngga isi manual lagi"; enum TERPISAH dari PayrollComponentEnum
        // biar gampang di-custom). Dipanggil LANGSUNG (bukan lewat runComponent()) karena
        // PayrollComponentRecipient::isEligible() otomatis false buat employee_type_id null
        // (eksekutif tidak punya tipe karyawan) - gerbang penerima itu tidak relevan buat
        // mereka, tapi toggle aktif/nonaktif per company (company_payroll_components) tetap
        // dihormati.
        if (in_array($user->id, User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS, true)) {
            foreach (ExecutiveAutoComponentEnum::toComponents() as $component) {
                $isActiveForCompany = $activeComponents->contains(
                    fn (CompanyPayrollComponent $c) => $c->component === $component->value
                );

                if (! $isActiveForCompany || $context->isProtected($component->value)) {
                    continue;
                }

                $calculatorClass = PayrollCalculatorRegistry::resolve($component);

                if ($calculatorClass) {
                    app($calculatorClass)->calculate($user, $payroll, $period, $context);
                }
            }

            app(ExecutiveAllowanceCalculator::class)->calculate($user, $payroll, $period, $context);

            $payroll->update([
                'gross_salary' => $context->grossSalary,
                'total_deductions' => $context->totalDeductions,
                'net_salary' => $context->grossSalary - $context->totalDeductions,
            ]);

            return $payroll;
        }

        // Urutan WAJIB: semua EARNING dulu -> Fee Claim (earning, always-on) -> semua
        // DEDUCTION kecuali Izin Seharian & Cuti -> Izin Seharian -> Cuti PALING TERAKHIR.
        // Izin Seharian butuh $context->grossSalary (T8) yang sudah final, lihat planning Fase
        // 2 §6. Cuti (khusus Melahirkan/Keguguran teknik "thp", lihat CutiCalculator) butuh
        // $context->grossSalary/totalDeductions yang sudah final dari SEMUA komponen lain
        // TERMASUK Izin Seharian - makanya ditaruh setelahnya, bukan sejajar.
        $earning = $activeComponents->filter(
            fn (CompanyPayrollComponent $c) => PayrollComponentEnum::tryFrom($c->component)?->type() === PayrollComponentTypeEnum::EARNING
        );
        $deduction = $activeComponents->filter(
            fn (CompanyPayrollComponent $c) => PayrollComponentEnum::tryFrom($c->component)?->type() === PayrollComponentTypeEnum::DEDUCTION
                && $c->component !== PayrollComponentEnum::IZIN_SEHARIAN->value
                && $c->component !== PayrollComponentEnum::CUTI->value
        );
        $izinSeharian = $activeComponents->first(
            fn (CompanyPayrollComponent $c) => $c->component === PayrollComponentEnum::IZIN_SEHARIAN->value
        );
        $cuti = $activeComponents->first(
            fn (CompanyPayrollComponent $c) => $c->component === PayrollComponentEnum::CUTI->value
        );

        foreach ($earning as $companyComponent) {
            $this->runComponent($companyComponent, $user, $payroll, $period, $context);
        }

        foreach ($deduction as $companyComponent) {
            $this->runComponent($companyComponent, $user, $payroll, $period, $context);
        }

        if ($izinSeharian) {
            $this->runComponent($izinSeharian, $user, $payroll, $period, $context);
        }

        if ($cuti) {
            $this->runComponent($cuti, $user, $payroll, $period, $context);
        }

        // Always-on, tidak digate lewat company_payroll_components - roster-nya sendiri
        // (bukan toggle company) yang menentukan ada-tidaknya baris, aman dipanggil untuk
        // semua user (no-op kalau roster kosong). Ditaruh paling akhir - baris manual ini
        // konsisten muncul di bagian bawah slip (source_type null diperlakukan sama seperti
        // manual adjustment lain di componentRows()).
        app(ExecutiveAllowanceCalculator::class)->calculate($user, $payroll, $period, $context);

        $payroll->update([
            'gross_salary' => $context->grossSalary,
            'total_deductions' => $context->totalDeductions,
            'net_salary' => $context->grossSalary - $context->totalDeductions,
        ]);

        return $payroll;
    }


    /**
     * Snapshot baris edited_manually=true dari payroll LAMA (sebelum dihapus) - dibaca sebagai
     * array polos (bukan Model), amount/unit_amount otomatis sudah didekripsi lewat cast
     * PayrollEncrypted saat diakses di sini, dienkripsi ulang otomatis saat di-create() balik ke
     * payroll baru.
     *
     * @return array<int, array<string, mixed>>
     */
    private function capturePreservedItems(Payroll $payroll): array
    {
        return $payroll->items()
            ->where('edited_manually', true)
            ->get()
            ->map(fn (PayrollItem $item) => [
                'name' => $item->name,
                'type' => $item->type,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_amount' => $item->unit_amount,
                'amount' => $item->amount,
                'note' => $item->note,
                'source_type' => $item->source_type,
            ])
            ->all();
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

        // Baris komponen ini sudah di-edit manual di payroll sebelumnya & sudah dibawa pindah
        // apa adanya ke payroll baru ini (lihat generateForEmployee()) - Calculator-nya WAJIB
        // di-skip, kalau tidak baris manualnya ketimpa balik oleh hasil hitungan otomatis.
        if (! $context->isProtected($component->value)) {
            $override = PayrollComponentOverride::activeFor($user->employee->id, $component->value);

            if ($override) {
                // Standing override (halaman "Override Komponen Gaji") - nilai dipakai APA
                // ADANYA, Calculator-nya sama sekali tidak dijalankan, dan gerbang "Penerima
                // Komponen Gaji" (isEligible) TIDAK relevan di sini - override adalah instruksi
                // eksplisit per-karyawan, bukan aturan tipe karyawan (2026-08-26, instruksi user).
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'name' => $component->label(),
                    'type' => $component->type()->value,
                    'quantity' => 1,
                    'amount' => (float) $override->amount,
                    'note' => $override->note,
                    'source_type' => $component->value,
                ]);

                $component->type() === \App\Enum\Payroll\PayrollComponentTypeEnum::EARNING
                    ? $context->addEarning((float) $override->amount)
                    : $context->addDeduction((float) $override->amount);
            } elseif ($isEligible) {
                $calculatorClass = PayrollCalculatorRegistry::resolve($component);

                if ($calculatorClass) {
                    app($calculatorClass)->calculate($user, $payroll, $period, $context);
                }
            }
        }

        // Komponen ini aktif tapi belum ada barisnya sama sekali — baik karena tipe karyawan ini
        // bukan penerima, atau eligible tapi Calculator-nya tidak menghasilkan apa-apa (nol/data
        // belum lengkap). Tetap harus KELIHATAN di slip sebagai Rp0, bukan hilang total. Fee
        // Claim/Pajak Jasa Dokter TIDAK lewat method ini (alwaysOn, lihat generateForEmployee()),
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
