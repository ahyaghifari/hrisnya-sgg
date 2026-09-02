<?php

namespace Tests\Feature\Payroll;

use App\Models\Master\Companies;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Payroll\PayrollPeriod;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Enum\CustomPermissionEnum;
use App\Livewire\Keuangan\ManualPayrollImportPage;
use App\Services\Payroll\ManualPayrollExcelImporter;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/**
 * Importer transkripsi payroll Excel -> Payroll/PayrollItem. Fixture xlsx dibuat inline
 * (angka file asli dirahasiakan). Lihat knowledge/markdown/import-payroll-excel-manual.md.
 */
class ManualPayrollExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;
    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'RSU', 'name' => 'RSU Test', 'location' => 'X', 'is_active' => true]);
        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]);
    }

    private function employee(string $nip, string $dob = '1990-05-10'): User
    {
        $u = User::factory()->create(['company_id' => $this->company->id, 'nip' => $nip]);
        UserProfiles::create(['user_id' => $u->id, 'date_of_birth' => $dob, 'email_pribadi' => "{$nip}@mail.test"]);

        return $u;
    }

    /** Header baris 1 layout Banjarbaru — importer memetakan kolom dari teks header ini. */
    private const HEADERS = [
        'A' => 'NO', 'B' => 'NAMA', 'C' => 'PERIODE GAJI', 'D' => 'TGL PEMBAYARAN GAJI',
        'E' => 'NIP', 'F' => 'NIK', 'G' => 'STATUS', 'H' => 'TANGGAL LAHIR', 'I' => 'JABATAN',
        'J' => 'Golongan Jabatan', 'K' => 'STATUS KARYAWAN', 'L' => 'Tanggal Masuk',
        'M' => 'Bulan Kerja', 'N' => 'Tunjangan Masa Kerja >24 bulan (Penambahan 50.000/Tahun)',
        'O' => 'Nilai JE', 'P' => 'Kelas', 'Q' => 'Gaji Dasar', 'R' => '% Kenaikan',
        'S' => 'Gaji Pokok', 'T' => 'Tunj. Jabatan Skema Baru', 'U' => 'Insentif',
        'V' => 'Tunj. Dokter/Jabatan Khusus', 'W' => 'Tunj. Jabatan/ Beban Kerja',
        'X' => 'TOTAL GAJI SKEMA BARU', 'Y' => 'Jasa Pelayanan Baru',
        'Z' => 'Jumlah Total Gaji+Jasa Pelayanan (SKEMA GAJI BARU)', 'AA' => 'tambahan jasa',
        'AB' => 'LEMBUR (Dihitung Stlh 2 Jam)', 'AC' => 'EXTRA SHIFT',
        'AD' => 'Jasa Supir Ambulance & Perwat (Rujukan Pasien)', 'AE' => 'Jasa Paket Infus',
        'AF' => 'Jumlah Terima Skema Baru', 'AG' => 'POTONGAN BPJS 1%',
        'AH' => 'POTONGAN BPJSTK (JHT 2 %)', 'AI' => 'JUMLAH TELAT > 5 MENIT/ BULAN',
        'AJ' => 'POTONGAN TELAT >5 MENIT',
        'AK' => 'JUMLAH TELAT >5 MENIT ISTIRAHAT ATAU TIDAK ABSEN',
        'AL' => 'POTONGAN TELAT >5 MENIT ISTIRAHAT ATAU TIDAK ABSEN',
        'AM' => 'POTONGAN IZIN TERLAMBAT MASUK ATAU PULANG CEPAT',
        'AN' => 'JMLH (CUTI, IZIN, SAKIT)', 'AO' => 'POTONGAN IZIN dan CUTI /HARI',
        'AP' => 'POTONGAN KOPERASI', 'AQ' => 'POTONGAN BON', 'AR' => 'TOTAL POTONGAN',
        'AS' => 'TOTAL GAJI YANG DITERIMA SKEMA BARU', 'AT' => 'NO REKENING BSI',
        'AU' => 'NAMA REKENING BSI', 'AV' => 'Alamat Email',
        'AX' => 'Jumlah Hari Efektif Kontrak / Cuti Melahirkan', 'AY' => 'Jumlah Potongan',
    ];

    /**
     * @param list<array<string,mixed>> $rows kolom => nilai, mulai baris 3
     * @param array<string,string>|null $headers override header baris 1 (mis. layout Barabai)
     */
    private function fixture(array $rows, ?array $headers = null): string
    {
        $ss = new Spreadsheet();
        $s = $ss->getActiveSheet();
        $s->setTitle('Gaji Skema Baru (JE)');
        foreach ($headers ?? self::HEADERS as $col => $label) {
            $s->setCellValue($col . '1', $label);
        }
        $s->setCellValue('B2', 46265); // serial periode

        $r = 3;
        foreach ($rows as $row) {
            foreach ($row as $col => $val) {
                $s->setCellValue($col . $r, $val);
            }
            $r++;
        }

        $path = tempnam(sys_get_temp_dir(), 'gaji_') . '.xlsx';
        (new XlsxWriter($ss))->save($path);

        return $path;
    }

    private function serial(string $date): int
    {
        return (int) \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime($date));
    }

    public function test_preview_skips_mitra_and_reports_unmatched_and_matches_zero_padded_nip(): void
    {
        $this->employee('01082019100');          // NIP HRIS 11 digit
        $this->employee('01082019101');

        $path = $this->fixture([
            ['B' => 'IGD/LAB'],                                                             // section header (E kosong)
            ['B' => 'dr. Mitra', 'E' => 'DU-01012020001', 'I' => 'Dokter', 'K' => 'Mitra', 'S' => 5_000_000], // skip
            ['A' => 1, 'B' => 'Andi Tetap', 'E' => '1082019100', 'I' => 'Perawat', 'K' => 'Karyawan Tetap',
                'L' => $this->serial('2019-08-01'), 'P' => 7, 'D' => $this->serial('2026-09-05'),
                'S' => 3_000_000, 'T' => 500_000, 'W' => 200_000, 'AG' => 30_000, 'AJ' => 10_000, 'AL' => 5_000, 'AS' => 3_655_000],
            ['A' => 2, 'B' => 'Budi Hilang', 'E' => '09099999999', 'I' => 'Analis', 'K' => 'Karyawan Kontrak', 'S' => 2_000_000, 'AS' => 2_000_000],
            ['B' => 'JUMLAH', 'S' => 999],                                                    // trailer -> stop
        ]);

        $out = app(ManualPayrollExcelImporter::class)->preview($path, $this->company->id);
        @unlink($path);

        $this->assertSame(1, $out['summary']['mitra_dilewati']);
        $this->assertCount(1, $out['matched']);
        $this->assertCount(1, $out['unmatched']);
        $this->assertSame('Budi Hilang', $out['unmatched'][0]['nama']);

        $m = $out['matched'][0];
        $this->assertSame('01082019100', $m['nip']);        // zero-padded match
        $this->assertSame(3_500_000.0, $m['gross']);        // 3jt + 500rb T (W/beban kerja DIABAIKAN)
        $this->assertSame(45_000.0, $m['deduction']);       // 30rb + (10rb+5rb AJ+AL)
        $this->assertSame(3_655_000.0, $m['net']);
        $this->assertSame(3_655_000.0, $m['net_excel']);
        $this->assertTrue($m['net_cocok']);

        // rincian per-komponen buat tabel pratinjau lebar
        $this->assertSame(3_000_000.0, $m['amounts']['gaji_pokok']);
        $this->assertSame(500_000.0, $m['amounts']['tunjangan_jabatan']);  // kolom T saja
        $this->assertSame(15_000.0, $m['amounts']['keterlambatan']);       // AJ+AL
        $this->assertSame(30_000.0, $m['amounts']['bpjs_kesehatan']);
    }

    public function test_import_creates_payroll_and_items_with_snapshot(): void
    {
        $emp = $this->employee('01082019100');
        $actor = User::factory()->create(['company_id' => $this->company->id]);

        $path = $this->fixture([
            ['A' => 1, 'B' => 'Andi', 'E' => '01082019100', 'I' => 'Perawat IGD', 'K' => 'Karyawan Tetap',
                'L' => $this->serial('2019-08-01'), 'P' => 7, 'D' => $this->serial('2026-09-05'),
                'S' => 3_000_000, 'T' => 500_000, 'W' => 200_000, 'U' => 0, 'AG' => 30_000, 'AP' => 0, 'AS' => 3_670_000],
            ['B' => 'JUMLAH'],
        ]);

        $res = app(ManualPayrollExcelImporter::class)->import($path, $this->period, $actor);
        @unlink($path);

        $this->assertSame(1, $res['dibuat']);

        $payroll = Payroll::where('user_id', $emp->id)->firstOrFail();
        $this->assertSame(3_500_000.0, (float) $payroll->gross_salary); // S + T (W diabaikan)
        $this->assertSame(30_000.0, (float) $payroll->total_deductions);
        $this->assertSame(3_670_000.0, (float) $payroll->net_salary);
        $this->assertFalse((bool) $payroll->is_paid);
        $this->assertSame('Perawat IGD', $payroll->import_meta['jabatan']);
        $this->assertSame(7, $payroll->import_meta['kelas']);
        $this->assertSame('2019-08-01', $payroll->import_meta['tanggal_masuk']);

        // Semua komponen tetap EARNINGS/DEDUCTIONS dibuat walau Rp0 (U, AP) -> slip lengkap.
        // tunjangan_jabatan = kolom T saja (W "beban kerja" diabaikan). Jasa Unit & potongan
        // khusus TIDAK (dinamis).
        $names = PayrollItem::where('payroll_id', $payroll->id)->pluck('source_type')->sort()->values()->all();
        $this->assertSame([
            'bon', 'bpjs_kesehatan', 'bpjs_ketenagakerjaan', 'extra_shift', 'gaji_pokok',
            'insentif', 'izin_cuti_gabung', 'izin_terlambat_pulang_cepat', 'jasa_pelayanan',
            'keterlambatan', 'koperasi', 'lembur', 'tunjangan_jabatan', 'tunjangan_khusus',
            'tunjangan_masa_kerja',
        ], $names);
        $this->assertSame(500_000.0, (float) PayrollItem::where('payroll_id', $payroll->id)->where('source_type', 'tunjangan_jabatan')->value('amount'));
        $this->assertSame(0.0, (float) PayrollItem::where('payroll_id', $payroll->id)->where('source_type', 'insentif')->value('amount'));
        $this->assertTrue((bool) PayrollItem::where('payroll_id', $payroll->id)->first()->edited_manually);

        // payment_date period keisi dari kolom D.
        $this->assertSame('2026-09-05', $this->period->fresh()->payment_date->format('Y-m-d'));
    }

    public function test_totals_follow_excel_subtotals_not_component_sum(): void
    {
        $emp = $this->employee('01082019100');
        $actor = User::factory()->create(['company_id' => $this->company->id]);

        // Komponen menjumlah ke 2.500.000 bruto / 100.000 potongan (net 2.400.000),
        // tapi subtotal Excel AF/AR/AS bilang lain — importer wajib ikut Excel.
        $path = $this->fixture([
            ['A' => 1, 'B' => 'Andi', 'E' => '01082019100', 'I' => 'Perawat', 'K' => 'Karyawan Tetap',
                'S' => 2_000_000, 'T' => 500_000, 'AG' => 100_000,
                'AF' => 2_570_000, 'AR' => 120_000, 'AS' => 2_450_000],
            ['B' => 'JUMLAH'],
        ]);

        $prev = app(ManualPayrollExcelImporter::class)->preview($path, $this->company->id)['matched'][0];
        $this->assertSame(2_570_000.0, $prev['gross']);
        $this->assertSame(120_000.0, $prev['deduction']);
        $this->assertSame(2_450_000.0, $prev['net']);

        app(ManualPayrollExcelImporter::class)->import($path, $this->period, $actor);
        @unlink($path);

        $payroll = Payroll::where('user_id', $emp->id)->firstOrFail();
        $this->assertSame(2_570_000.0, (float) $payroll->gross_salary);
        $this->assertSame(120_000.0, (float) $payroll->total_deductions);
        $this->assertSame(2_450_000.0, (float) $payroll->net_salary);
    }

    public function test_reads_formula_cells_not_the_formula_text(): void
    {
        $this->employee('01082019100');

        // Kolom N (Tunjangan Masa Kerja) & AF/AR/AS sebagai RUMUS — importer wajib menghitungnya,
        // bukan membaca "=..." jadi 0. (Kasus nyata: file dari LibreOffice tanpa cache hasil rumus.)
        $path = $this->fixture([
            ['A' => 1, 'B' => 'Andi', 'E' => '01082019100', 'I' => 'Perawat', 'K' => 'Karyawan Tetap',
                'S' => 3_000_000, 'N' => '=250000+50000', 'AG' => 100_000,
                'AF' => '=3000000+300000', 'AR' => '=100000', 'AS' => '=AF3-AR3'],
            ['B' => 'JUMLAH'],
        ]);

        $m = app(ManualPayrollExcelImporter::class)->preview($path, $this->company->id)['matched'][0];
        @unlink($path);

        $this->assertSame(300_000.0, $m['amounts']['tunjangan_masa_kerja']);
        $this->assertSame(3_300_000.0, $m['gross']);
        $this->assertSame(100_000.0, $m['deduction']);
        $this->assertSame(3_200_000.0, $m['net']);
    }

    /** Header baris 1 layout Barabai: TANPA "Tunj. Dokter/Jabatan Khusus" (V) & "Tunj.
     *  Jabatan/Beban Kerja" (W), jadi semua kolom sesudahnya geser −2. Importer harus tetap
     *  petakan kolom yang benar dari teks header. */
    private const HEADERS_BARABAI = [
        'A' => 'NO', 'B' => 'NAMA', 'C' => 'PERIODE GAJI', 'D' => 'TGL PEMBAYARAN GAJI',
        'E' => 'NIP', 'F' => 'NIK', 'G' => 'STATUS', 'H' => 'TANGGAL LAHIR', 'I' => 'JABATAN',
        'J' => 'Golongan Jabatan', 'K' => 'STATUS KARYAWAN', 'L' => 'Tanggal Masuk',
        'M' => 'Bulan Kerja', 'N' => 'Tunjangan Masa Kerja >24 bulan (Penambahan 50.000/Tahun)',
        'O' => 'Nilai JE', 'P' => 'Kelas', 'Q' => 'Gaji Dasar', 'R' => '% Kenaikan',
        'S' => 'Gaji Pokok', 'T' => 'Tunj. Jabatan Skema Baru', 'U' => 'Insentif',
        'V' => 'TOTAL GAJI SKEMA BARU', 'W' => 'Jasa Pelayanan Baru',
        'X' => 'Jumlah Total Gaji+Jasa Pelayanan (SKEMA GAJI BARU)', 'Y' => 'tambahan jasa',
        'Z' => 'LEMBUR (Dihitung Stlh 2 Jam)', 'AA' => 'EXTRA SHIFT',
        'AB' => 'Jasa Supir Ambulance & Perwat (Rujukan Pasien)', 'AC' => 'Jasa Paket Infus',
        'AD' => 'Jumlah Terima Skema Baru', 'AE' => 'POTONGAN BPJS 1%',
        'AF' => 'POTONGAN BPJSTK (JHT 2 %)', 'AG' => 'JUMLAH TELAT > 5 MENIT/ BULAN',
        'AH' => 'POTONGAN TELAT >5 MENIT',
        'AI' => 'JUMLAH TELAT >5 MENIT ISTIRAHAT ATAU TIDAK ABSEN',
        'AJ' => 'POTONGAN TELAT >5 MENIT ISTIRAHAT ATAU TIDAK ABSEN',
        'AK' => 'POTONGAN IZIN TERLAMBAT MASUK ATAU PULANG CEPAT',
        'AL' => 'JMLH (CUTI, IZIN, SAKIT)', 'AM' => 'POTONGAN IZIN dan CUTI /HARI',
        'AN' => 'POTONGAN KOPERASI', 'AO' => 'POTONGAN BON', 'AP' => 'TOTAL POTONGAN',
        'AQ' => 'TOTAL GAJI YANG DITERIMA SKEMA BARU', 'AR' => 'NO REKENING BSI',
        'AS' => 'NAMA REKENING BSI', 'AT' => 'Alamat Email',
    ];

    public function test_barabai_shifted_layout_maps_by_header(): void
    {
        $emp = $this->employee('01082019100');
        $actor = User::factory()->create(['company_id' => $this->company->id]);

        // Kolom pakai huruf layout Barabai: T=tunj jabatan, W=jasa pelayanan, Z=lembur,
        // AD=bruto, AE=bpjs kes, AG/AI=count telat, AH/AJ=potongan telat, AP=total potongan,
        // AQ=net. TIDAK ada V (tunj khusus) & AX (potongan khusus).
        $path = $this->fixture([
            ['A' => 1, 'B' => 'Andi', 'E' => '01082019100', 'I' => 'Perawat', 'K' => 'Karyawan Tetap',
                'S' => 2_000_000, 'T' => 600_000, 'N' => 300_000, 'W' => 400_000, 'Z' => 150_000,
                'AE' => 100_000, 'AG' => 2, 'AH' => 25_000, 'AI' => 1, 'AJ' => 10_000,
                'AD' => 3_450_000, 'AP' => 135_000, 'AQ' => 3_315_000],
            ['B' => 'JUMLAH'],
        ], self::HEADERS_BARABAI);

        $m = app(ManualPayrollExcelImporter::class)->preview($path, $this->company->id)['matched'][0];

        $this->assertSame(3_450_000.0, $m['gross']);      // kolom AD
        $this->assertSame(135_000.0, $m['deduction']);    // kolom AP
        $this->assertSame(3_315_000.0, $m['net']);        // kolom AQ
        $this->assertSame(600_000.0, $m['amounts']['tunjangan_jabatan']);   // T
        $this->assertSame(400_000.0, $m['amounts']['jasa_pelayanan']);      // W (bukan Y)
        $this->assertSame(300_000.0, $m['amounts']['tunjangan_masa_kerja']); // N
        $this->assertSame(150_000.0, $m['amounts']['lembur']);              // Z
        $this->assertSame(100_000.0, $m['amounts']['bpjs_kesehatan']);      // AE
        $this->assertSame(35_000.0, $m['amounts']['keterlambatan']);        // AH+AJ

        app(ManualPayrollExcelImporter::class)->import($path, $this->period, $actor);
        @unlink($path);

        $payroll = Payroll::where('user_id', $emp->id)->firstOrFail();
        $this->assertSame(3_450_000.0, (float) $payroll->gross_salary);
        $this->assertSame(3_315_000.0, (float) $payroll->net_salary);
        // Telat "3x/Bulan" (2 + 1).
        $telat = PayrollItem::where('payroll_id', $payroll->id)->where('source_type', 'keterlambatan')->first();
        $this->assertSame('3x/Bulan', $telat->note);
        // Tak ada tunjangan_khusus (kolomnya memang tak ada di layout Barabai).
        $this->assertFalse(PayrollItem::where('payroll_id', $payroll->id)->where('source_type', 'tunjangan_khusus')->exists());
    }

    public function test_event_counts_and_special_deduction(): void
    {
        $emp = $this->employee('01082019100');
        $actor = User::factory()->create(['company_id' => $this->company->id]);

        $path = $this->fixture([
            ['A' => 1, 'B' => 'Andi', 'E' => '01082019100', 'I' => 'Perawat', 'K' => 'Karyawan Tetap',
                'S' => 3_000_000,
                'AJ' => 20_000, 'AL' => 10_000, 'AI' => 2, 'AK' => 1,   // telat: potong 30rb, 3 kejadian
                'AO' => 150_000, 'AN' => 4,                             // izin/cuti: potong 150rb, 4 hari
                'AX' => 250_000,                                        // potongan khusus
                'AS' => 2_570_000],
            ['B' => 'JUMLAH'],
        ]);

        app(ManualPayrollExcelImporter::class)->import($path, $this->period, $actor);
        @unlink($path);

        $payroll = Payroll::where('user_id', $emp->id)->firstOrFail();

        $telat = PayrollItem::where('payroll_id', $payroll->id)->where('source_type', 'keterlambatan')->first();
        $this->assertSame(30_000.0, (float) $telat->amount);
        $this->assertSame(3.0, (float) $telat->quantity);
        $this->assertSame('3x/Bulan', $telat->note);

        $izinCuti = PayrollItem::where('payroll_id', $payroll->id)->where('source_type', 'izin_cuti_gabung')->first();
        $this->assertSame(4.0, (float) $izinCuti->quantity);
        $this->assertSame('4x', $izinCuti->note);

        $khusus = PayrollItem::where('payroll_id', $payroll->id)->where('source_type', 'potongan_khusus')->first();
        $this->assertNotNull($khusus);
        $this->assertSame('deduction', $khusus->type);
        $this->assertSame(250_000.0, (float) $khusus->amount);

        // AX juga tercatat di payroll_manual_lines.
        $this->assertDatabaseHas('payroll_manual_lines', [
            'payroll_period_id' => $this->period->id,
            'user_id' => $emp->id,
            'component' => 'potongan_khusus',
        ]);

        $this->assertSame(430_000.0, (float) $payroll->total_deductions); // 30rb + 150rb + 250rb
    }

    public function test_tunjangan_khusus_label_is_per_company(): void
    {
        $emp = $this->employee('01082019100');
        $actor = User::factory()->create(['company_id' => $this->company->id]);

        // company code default (RSU) -> label fallback "Tunjangan Khusus"
        $path = $this->fixture([
            ['A' => 1, 'B' => 'Andi', 'E' => '01082019100', 'K' => 'Karyawan Tetap', 'S' => 3_000_000, 'V' => 400_000, 'AS' => 3_400_000],
            ['B' => 'JUMLAH'],
        ]);
        app(ManualPayrollExcelImporter::class)->import($path, $this->period, $actor);
        @unlink($path);

        $item = PayrollItem::where('payroll_id', Payroll::where('user_id', $emp->id)->value('id'))
            ->where('source_type', 'tunjangan_khusus')->first();
        $this->assertNotNull($item);
        $this->assertSame('earning', $item->type);
        $this->assertSame(400_000.0, (float) $item->amount);          // baca balik dari kolom terenkripsi
        $this->assertSame('Tunjangan Khusus', $item->name);

        // company dengan code 0001 -> label hardcode "Tunjangan Dokter/Jabatan Khusus"
        $rsu = Companies::create(['code' => '0001', 'name' => 'RSU 0001', 'location' => 'X', 'is_active' => true]);
        $period0001 = PayrollPeriod::create(['company_id' => $rsu->id, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
        $emp2 = User::factory()->create(['company_id' => $rsu->id, 'nip' => '02082019200']);
        UserProfiles::create(['user_id' => $emp2->id, 'date_of_birth' => '1991-01-01']);

        $path2 = $this->fixture([
            ['A' => 1, 'B' => 'Budi', 'E' => '02082019200', 'K' => 'Karyawan Tetap', 'S' => 3_000_000, 'V' => 500_000, 'AS' => 3_500_000],
            ['B' => 'JUMLAH'],
        ]);
        app(ManualPayrollExcelImporter::class)->import($path2, $period0001, $actor);
        @unlink($path2);

        $item2 = PayrollItem::where('payroll_id', Payroll::where('user_id', $emp2->id)->value('id'))
            ->where('source_type', 'tunjangan_khusus')->first();
        $this->assertSame('Tunjangan Dokter/Jabatan Khusus', $item2->name);
    }

    public function test_slip_row_order_for_imported_payroll(): void
    {
        $emp = $this->employee('01082019100');
        $actor = User::factory()->create(['company_id' => $this->company->id]);

        $path = $this->fixture([
            ['A' => 1, 'B' => 'Andi', 'E' => '01082019100', 'K' => 'Karyawan Tetap',
                'S' => 3_000_000, 'U' => 100_000, 'V' => 400_000, 'AB' => 50_000,   // earning: gaji, insentif, tunj khusus, lembur
                'AG' => 30_000, 'AO' => 150_000, 'AP' => 20_000, 'AX' => 250_000,   // deduction: bpjs, izin+cuti, koperasi, khusus
                'AS' => 3_100_000],
            ['B' => 'JUMLAH'],
        ]);
        app(ManualPayrollExcelImporter::class)->import($path, $this->period, $actor);
        @unlink($path);

        $payroll = Payroll::with('items')->where('user_id', $emp->id)->firstOrFail();
        $rows = app(PayrollService::class)->componentRows($payroll);

        // Semua komponen tetap dibuat (walau Rp0) -> slip lengkap; urutan ikut PayrollComponentEnum
        // + slot pecahan buat source_type kustom (tunjangan_khusus setelah insentif, izin_cuti_gabung
        // di slot Izin/Cuti, potongan_khusus setelah Bon).
        $this->assertSame(
            ['gaji_pokok', 'tunjangan_jabatan', 'tunjangan_masa_kerja', 'insentif', 'tunjangan_khusus', 'lembur', 'jasa_pelayanan', 'extra_shift'],
            array_column($rows['earnings'], 'component')
        );
        $this->assertSame(
            ['bpjs_kesehatan', 'bpjs_ketenagakerjaan', 'keterlambatan', 'izin_terlambat_pulang_cepat', 'izin_cuti_gabung', 'koperasi', 'bon', 'potongan_khusus'],
            array_column($rows['deductions'], 'component')
        );
    }

    public function test_jasa_unit_one_row_per_jenis(): void
    {
        $emp = $this->employee('01082019100');
        $actor = User::factory()->create(['company_id' => $this->company->id]);

        // AA (Tambahan Jasa) + AE (Paket Infus) terisi, AD (Ambulans) kosong -> 2 baris.
        $path = $this->fixture([
            ['A' => 1, 'B' => 'Andi', 'E' => '01082019100', 'K' => 'Karyawan Tetap',
                'S' => 3_000_000, 'AA' => 100_000, 'AE' => 50_000, 'AS' => 3_150_000],
            ['B' => 'JUMLAH'],
        ]);
        app(ManualPayrollExcelImporter::class)->import($path, $this->period, $actor);
        @unlink($path);

        $payrollId = Payroll::where('user_id', $emp->id)->value('id');
        $jasa = PayrollItem::where('payroll_id', $payrollId)->where('source_type', 'jasa_unit')
            ->pluck('amount', 'name')->map(fn ($v) => (float) $v)->all();

        $this->assertSame(['Tambahan Jasa' => 100_000.0, 'Paket Infus' => 50_000.0], $jasa);
    }

    public function test_special_deduction_skipped_when_ax_empty(): void
    {
        $emp = $this->employee('01082019100');
        $actor = User::factory()->create(['company_id' => $this->company->id]);

        $path = $this->fixture([
            ['A' => 1, 'B' => 'Andi', 'E' => '01082019100', 'K' => 'Karyawan Tetap', 'S' => 3_000_000, 'AS' => 3_000_000],
            ['B' => 'JUMLAH'],
        ]);
        app(ManualPayrollExcelImporter::class)->import($path, $this->period, $actor);
        @unlink($path);

        $payroll = Payroll::where('user_id', $emp->id)->firstOrFail();
        $this->assertSame(0, PayrollItem::where('payroll_id', $payroll->id)->where('source_type', 'potongan_khusus')->count());
        $this->assertDatabaseMissing('payroll_manual_lines', ['payroll_period_id' => $this->period->id, 'component' => 'potongan_khusus']);
    }

    public function test_page_renders_for_user_with_import_payroll_manual_permission(): void
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::IMPORT_PAYROLL_MANUAL->value, 'guard_name' => 'web']);
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->givePermissionTo(CustomPermissionEnum::IMPORT_PAYROLL_MANUAL->value);

        $this->actingAs($user);
        Livewire::test(ManualPayrollImportPage::class)
            ->assertOk()
            ->assertSee('Import Payroll Manual')
            ->assertSee('Periode Gaji Tujuan');
    }

    public function test_multi_company_user_sees_periods_of_other_company(): void
    {
        // User dari company LAIN + boleh pilih perusahaan lain -> global scope BelongsToCompany
        // pada PayrollPeriod tidak boleh menyembunyikan periode $this->company.
        Permission::firstOrCreate(['name' => CustomPermissionEnum::IMPORT_PAYROLL_MANUAL->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value, 'guard_name' => 'web']);

        $otherCompany = Companies::create(['code' => 'OTH', 'name' => 'Company Lain', 'location' => 'X', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $otherCompany->id]);
        $user->givePermissionTo([CustomPermissionEnum::IMPORT_PAYROLL_MANUAL->value, CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value]);

        $this->actingAs($user);
        $c = Livewire::test(ManualPayrollImportPage::class)->set('companyId', $this->company->id);

        $this->assertTrue($c->get('periods')->contains('id', $this->period->id));
        $c->set('periodId', $this->period->id);
        $this->assertSame($this->period->id, $c->get('selectedPeriod')?->id);
    }

    public function test_page_forbidden_without_permission(): void
    {
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));
        Livewire::test(ManualPayrollImportPage::class)->assertForbidden();
    }

    public function test_page_shows_payroll_table_and_mark_paid_action(): void
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::IMPORT_PAYROLL_MANUAL->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Payroll', 'guard_name' => 'web']);
        $admin = User::factory()->create(['company_id' => $this->company->id]);
        $admin->givePermissionTo([CustomPermissionEnum::IMPORT_PAYROLL_MANUAL->value, 'Update:Payroll']);

        $emp = $this->employee('01082019100');
        $actor = User::factory()->create(['company_id' => $this->company->id]);
        $path = $this->fixture([
            ['A' => 1, 'B' => 'Andi', 'E' => '01082019100', 'K' => 'Karyawan Tetap', 'S' => 3_000_000, 'AS' => 3_000_000],
            ['B' => 'JUMLAH'],
        ]);
        app(ManualPayrollExcelImporter::class)->import($path, $this->period, $actor);
        @unlink($path);

        $payroll = Payroll::where('user_id', $emp->id)->firstOrFail();

        $this->actingAs($admin);
        Livewire::test(ManualPayrollImportPage::class)
            ->set('periodId', $this->period->id)
            ->assertSee($emp->name)
            ->assertSee('Belum Dibayar')
            ->set('selected', [$payroll->id])
            ->call('markSelectedAsPaid')
            ->assertSet('selected', []);

        $this->assertTrue((bool) $payroll->fresh()->is_paid);
    }

    public function test_slip_status_filter_and_resend_failed(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        Permission::firstOrCreate(['name' => CustomPermissionEnum::IMPORT_PAYROLL_MANUAL->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Payroll', 'guard_name' => 'web']);
        $admin = User::factory()->create(['company_id' => $this->company->id]);
        $admin->givePermissionTo([CustomPermissionEnum::IMPORT_PAYROLL_MANUAL->value, 'Update:Payroll']);

        // 3 payroll di periode: 1 terkirim, 1 gagal, 1 belum-kirim (sudah dibayar).
        $mk = fn (array $attr) => Payroll::create(array_merge([
            'user_id' => $this->employee('nip' . fake()->unique()->numberBetween(1000, 9999))->id,
            'payroll_period_id' => $this->period->id,
            'gross_salary' => 1_000_000, 'total_deductions' => 0, 'net_salary' => 1_000_000,
            'is_paid' => true,
        ], $attr));

        $sent = $mk(['slip_sent_at' => now()]);
        $failed = $mk(['slip_send_failed_at' => now(), 'slip_send_error' => 'SMTP timeout']);
        $unsent = $mk([]);

        $this->actingAs($admin);
        $c = Livewire::test(ManualPayrollImportPage::class)->set('periodId', $this->period->id);

        $c->assertSet('slipStats.sent', 1)->assertSet('slipStats.failed', 1)->assertSet('slipStats.unsent', 1);

        // filter "failed" -> cuma baris gagal
        $c->set('slipStatus', 'failed');
        $this->assertSame([$failed->id], $c->get('payrolls')->pluck('id')->all());

        // kirim ulang yang gagal
        $c->call('resendFailedSlips');
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendPayrollSlipJob::class, 1);
    }

    public function test_reimport_replaces_unpaid_but_blocks_when_paid(): void
    {
        $emp = $this->employee('01082019100');
        $actor = User::factory()->create(['company_id' => $this->company->id]);
        $make = fn (int $gp) => $this->fixture([
            ['A' => 1, 'B' => 'Andi', 'E' => '01082019100', 'I' => 'Perawat', 'K' => 'Karyawan Tetap', 'S' => $gp, 'AS' => $gp],
            ['B' => 'JUMLAH'],
        ]);

        $p1 = $make(3_000_000);
        app(ManualPayrollExcelImporter::class)->import($p1, $this->period, $actor);
        @unlink($p1);
        $this->assertSame(3_000_000.0, (float) Payroll::where('user_id', $emp->id)->value('gross_salary'));

        // Re-import (belum dibayar) -> baris lama diganti, tidak dobel.
        $p2 = $make(3_100_000);
        app(ManualPayrollExcelImporter::class)->import($p2, $this->period, $actor);
        @unlink($p2);
        $this->assertSame(1, Payroll::where('payroll_period_id', $this->period->id)->count());
        $this->assertSame(3_100_000.0, (float) Payroll::where('user_id', $emp->id)->value('gross_salary'));

        // Tandai dibayar -> import ulang ditolak.
        Payroll::where('payroll_period_id', $this->period->id)->update(['is_paid' => true]);
        $p3 = $make(3_200_000);
        $this->expectException(\RuntimeException::class);
        try {
            app(ManualPayrollExcelImporter::class)->import($p3, $this->period, $actor);
        } finally {
            @unlink($p3);
        }
    }
}
