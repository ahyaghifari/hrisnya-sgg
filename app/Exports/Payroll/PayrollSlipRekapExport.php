<?php

namespace App\Exports\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Exports\Payroll\Concerns\GroupsPayrollsByDivision;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Services\Payroll\GajiPokokBreakdownResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Rekap gaji 1 periode, format & urutan kolom mengikuti contoh Excel finance
 * (knowledge/gaji-juni.xlsx, 2026-08-22 - font Times New Roman 11 + highlight header per kolom
 * ikut persis file itu, lihat HEADER_HIGHLIGHT_COLORS & styles()) - dikelompokkan per DIVISI (org level 2, lihat
 * Employee::getDivisionAttribute()) dengan baris judul divisi disisipkan di antara baris
 * karyawan (kolom lain di baris judul dibiarkan kosong, bukan di-merge - cukup buat dibaca
 * finance, tidak perlu identik piksel).
 */
class PayrollSlipRekapExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithStyles, WithCustomValueBinder, WithEvents
{
    use GroupsPayrollsByDivision;

    private const CORE_EARNINGS = [
        PayrollComponentEnum::GAJI_POKOK,
        PayrollComponentEnum::TUNJANGAN_JABATAN,
        PayrollComponentEnum::TUNJANGAN_TRANSPORTASI,
        PayrollComponentEnum::TUNJANGAN_MASA_KERJA,
        PayrollComponentEnum::INSENTIF,
    ];

    private const CURRENCY_FORMAT = '"Rp" #,##0';

    /** Kolom JUMLAH (hitungan hari/kejadian, bukan uang) - eksplisit angka polos, bukan
     * biarin "General" (yang bisa kebawa gaya accounting/currency dari style default kalau
     * dibuka di Excel, laporan user 2026-08-26). */
    private const PLAIN_NUMBER_FORMAT = '0';

    /** Warna highlight per label header, niru persis referensi finance (knowledge/gaji-juni.xlsx)
     * - kolom yang tidak disebut di sini header-nya putih polos (tidak diberi fill), sama seperti
     * mayoritas kolom di file itu. */
    private const HEADER_HIGHLIGHT_COLORS = [
        'Gaji Dasar' => 'FFFF00',
        'Gaji Pokok' => 'E6B9B8',
        'TOTAL GAJI' => 'FFFF00',
        'Jumlah Terima' => 'D99594',
        'JUMLAH IZIN TERLAMBAT MASUK ATAU PULANG CEPAT' => 'BFBFBF',
        'JUMLAH CUTI' => 'BFBFBF',
        'JUMLAH IZIN SEHARIAN' => 'BFBFBF',
        'POTONGAN KOPERASI' => 'B6DDE8',
        'POTONGAN BON' => 'FBD4B4',
    ];

    /** @var array<int, array{component: ?PayrollComponentEnum, label: string, get: \Closure, format: ?string, forceText: bool, editable: bool}> */
    private array $columns;

    /** Baris (1-based, sudah termasuk offset heading) yang berisi judul divisi - buat styling di registerEvents(). */
    private array $groupHeaderRows = [];

    /** @param Collection<int, Payroll> $payrolls */
    public function __construct(protected Collection $payrolls, protected string $companyId)
    {
        $this->columns = $this->buildColumns();
    }

    public function collection(): Collection
    {
        $grouped = $this->groupedByDivision($this->payrolls, $this->companyId);

        $rows = collect();
        $rowNumber = 1; // baris 1 = heading, data mulai baris 2

        foreach ($grouped as $divisionName => $payrolls) {
            $rowNumber++;
            $this->groupHeaderRows[] = $rowNumber;
            $rows->push($this->groupHeaderRow($divisionName));

            $no = 1; // reset per divisi (permintaan user) - bukan nomor urut global 1 file.
            foreach ($this->sortWithinDivision($payrolls) as $payroll) {
                $rowNumber++;
                $rows->push($this->payrollRow($payroll, $no));
                $no++;
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return array_column($this->columns, 'label');
    }

    /**
     * Definisi kolom lengkap (label, PayrollComponentEnum terkait, flag `editable`) - dipakai
     * PayrollRevisionImport supaya header yang dibaca saat import PERSIS sama dengan yang
     * ditulis saat export (satu sumber kebenaran, tidak pernah desinkron - lihat komentar
     * class). Cuma kolom `editable` yang boleh ditulis balik ke database; kolom hasil hitungan
     * (TOTAL GAJI/Jumlah Terima/dst) dan identitas dibiarkan read-only.
     *
     * @return array<int, array{component: ?PayrollComponentEnum, label: string, get: \Closure, format: ?string, forceText: bool, editable: bool}>
     */
    public function columnDefinitions(): array
    {
        return $this->columns;
    }

    /** Font & header niru persis file finance (knowledge/gaji-juni.xlsx) - Times New Roman 11,
     * header TIDAK bold/berwarna seragam, tiap kolom pakai highlight sendiri kalau ada
     * (HEADER_HIGHLIGHT_COLORS, diterapkan di registerEvents() karena butuh loop per kolom). */
    public function styles(Worksheet $sheet)
    {
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(11);

        return [
            1 => [
                'font' => ['bold' => false, 'color' => ['rgb' => '000000']],
                'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    /**
     * Kolom bertanda `forceText` (ID/NIK/No Rekening) dipaksa DataType::TYPE_STRING lewat
     * WithCustomValueBinder - kalau cuma diformat '@' lewat NumberFormat SETELAH nilainya
     * ditulis, angka yang kelihatan seperti numerik (mis. "00123") sudah keburu dikonversi jadi
     * float & kehilangan nol di depan sebelum sempat diformat.
     */
    public function bindValue(Cell $cell, $value)
    {
        $columnIndex = Coordinate::columnIndexFromString($cell->getColumn());
        $column = $this->columns[$columnIndex - 1] ?? null;

        if ($column && $column['forceText'] && $value !== null && $value !== '') {
            // NO REKENING: cuma dipaksa teks kalau ADA nol di depan (kalau tidak, biarkan
            // numerik apa adanya - permintaan user 2026-08-29). ID/NIK tetap selalu teks
            // (tanpa nol depan pun bisa kepanjangan & kehilangan presisi kalau jadi float).
            if ($column['label'] === 'NO REKENING' && ! str_starts_with((string) $value, '0')) {
                return parent::bindValue($cell, $value);
            }

            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                foreach ($this->columns as $index => $column) {
                    $letter = Coordinate::stringFromColumnIndex($index + 1);

                    // Header panjang (mis. "JUMLAH IZIN TERLAMBAT MASUK ATAU PULANG CEPAT") dikasih
                    // lebar tetap biar benar-benar wrap ke bawah, bukan malah bikin kolom melebar -
                    // autosize PhpSpreadsheet menghitung dari teks TANPA wrap, jadi 2 hal ini
                    // saling bertentangan kalau dipakai bareng untuk kolom yang sama.
                    if ($column['label'] === 'PANGKAT') {
                        // Nama pangkat kadang panjang - kolom dilebarkan & isinya di-wrap ke bawah
                        // (bukan autosize melebar tak terbatas), biar tetap kebaca penuh.
                        $sheet->getColumnDimension($letter)->setAutoSize(false)->setWidth(24);
                        $sheet->getStyle("{$letter}2:{$letter}{$lastRow}")->getAlignment()->setWrapText(true);
                    } elseif ($column['label'] === '% Kenaikan') {
                        $sheet->getColumnDimension($letter)->setAutoSize(false)->setWidth(8);
                    } elseif (mb_strlen($column['label']) > 20) {
                        $sheet->getColumnDimension($letter)->setAutoSize(false)->setWidth(18);
                    } else {
                        $sheet->getColumnDimension($letter)->setAutoSize(true);
                    }

                    if ($column['format']) {
                        $sheet->getStyle("{$letter}2:{$letter}{$lastRow}")->getNumberFormat()->setFormatCode($column['format']);
                    }

                    if ($column['label'] === 'NO REKENING') {
                        $sheet->getStyle("{$letter}2:{$letter}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }

                    if ($color = array_key_exists('color', $column) ? $column['color'] : (self::HEADER_HIGHLIGHT_COLORS[$column['label']] ?? null)) {
                        $sheet->getStyle("{$letter}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                    }
                }

                // Row height 98 & freeze C2 niru persis referensi (knowledge/gaji-juni.xlsx) -
                // C2 (bukan A2) supaya kolom NO+NAMA ikut freeze bareng header saat discroll ke
                // kanan (banyak kolom komponen gaji).
                $sheet->getRowDimension(1)->setRowHeight(98);

                foreach ($this->groupHeaderRows as $rowNumber) {
                    $sheet->getStyle("A{$rowNumber}:" . Coordinate::stringFromColumnIndex(count($this->columns)) . $rowNumber)
                        ->applyFromArray([
                            'font' => ['bold' => true, 'size' => 14],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                        ]);
                }

                $lastColumn = Coordinate::stringFromColumnIndex(count($this->columns));
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
                ]);

                $sheet->freezePane('C2');
            },
        ];
    }

    private function groupHeaderRow(string $name): array
    {
        return array_pad(['', $name], count($this->columns), '');
    }

    private function payrollRow(Payroll $payroll, int $no): array
    {
        return array_map(fn (array $column) => $column['get']($payroll, $no), $this->columns);
    }

    /**
     * Komponen yang case-nya "alwaysOn()" tidak butuh baris `company_payroll_components` buat
     * aktif (lihat PayrollComponentEnum::alwaysOn()) - diperlakukan aktif juga di sini.
     */
    private function activeComponentValues(): array
    {
        $toggled = CompanyPayrollComponent::query()
            ->where('company_id', $this->companyId)
            ->where('is_active', true)
            ->pluck('component')
            ->all();

        $alwaysOn = array_map(fn (PayrollComponentEnum $c) => $c->value, PayrollComponentEnum::alwaysOn());

        return array_unique([...$toggled, ...$alwaysOn]);
    }

    /**
     * Delegasi ke GajiPokokBreakdownResolver (SATU sumber rumus, dipakai bareng
     * PajakGajiPokokExport - 2026-08-22, sebelumnya duplikat di sini). Di-memoize per Payroll
     * (spl_object_id) supaya relasi pangkat->pangkatSalary cuma ditelusuri sekali walau dipakai
     * 2 kolom (Gaji Dasar & % Kenaikan).
     *
     * @return array{0: ?float, 1: ?float} [base, percentKenaikan]
     */
    private function gajiPokokBreakdown(Payroll $payroll, array &$cache): array
    {
        $key = spl_object_id($payroll);

        return $cache[$key] ??= GajiPokokBreakdownResolver::resolve($payroll);
    }

    /** @return array<int, array{component: ?PayrollComponentEnum, label: string, get: \Closure, format: ?string, forceText: bool}> */
    private function buildColumns(): array
    {
        $active = $this->activeComponentValues();
        // Banjarbaru & Barabai: kolom export dibuat identik (semua komponen), abaikan toggle per perusahaan.
        $sameLayout = in_array(\App\Models\Master\Companies::whereKey($this->companyId)->value('code'), \App\Services\Payroll\ManualPayrollExcelImporter::COMPANY_CODES, true);
        $isActive = fn (?PayrollComponentEnum $component) => $sameLayout || $component === null || in_array($component->value, $active, true);

        $itemFor = fn (Payroll $payroll, PayrollComponentEnum $component) => $payroll->items->firstWhere('source_type', $component->value);
        $amountFor = fn (Payroll $payroll, PayrollComponentEnum $component) => (float) ($itemFor($payroll, $component)?->amount ?? 0);
        $quantityFor = fn (Payroll $payroll, PayrollComponentEnum $component) => (float) ($itemFor($payroll, $component)?->quantity ?? 0);

        $coreEarningTotal = fn (Payroll $payroll) => collect(self::CORE_EARNINGS)->sum(fn ($c) => $amountFor($payroll, $c));
        $otherEarningTotal = function (Payroll $payroll) {
            $coreValues = array_map(fn ($c) => $c->value, self::CORE_EARNINGS);

            return $payroll->items
                ->where('type', 'earning')
                ->reject(fn (PayrollItem $item) => in_array($item->source_type, $coreValues, true))
                ->sum(fn (PayrollItem $item) => (float) $item->amount);
        };

        $profileOf = fn (Payroll $payroll) => $payroll->employee;
        $joiningDateOf = function (Payroll $payroll) use ($profileOf) {
            $joiningDate = $profileOf($payroll)?->joining_date;

            return $joiningDate ? Carbon::parse($joiningDate) : null;
        };

        $breakdownCache = [];
        $gajiPokokBase = fn (Payroll $payroll) => $this->gajiPokokBreakdown($payroll, $breakdownCache)[0];
        $gajiPokokPercent = fn (Payroll $payroll) => $this->gajiPokokBreakdown($payroll, $breakdownCache)[1];

        // Layout Banjarbaru & Barabai: urutan kolom + warna header ikut Excel sumber
        // (knowledge/gaji-agustus.xlsx & gaji-agustus-barabai.xlsx), gabungan keduanya.
        $money = self::CURRENCY_FORMAT;
        $sumOf = fn (Payroll $p, array $types) => (float) $p->items->whereIn('source_type', $types)->sum(fn ($i) => (float) $i->amount);
        $qtyOf = fn (Payroll $p, array $types) => (float) $p->items->whereIn('source_type', $types)->sum(fn ($i) => (float) $i->quantity);
        $jasaUnit = fn (Payroll $p, string $name) => (float) $p->items->where('source_type', 'jasa_unit')->where('name', $name)->sum(fn ($i) => (float) $i->amount);
        $izinCutiTypes = ['izin_cuti_gabung', 'cuti', 'izin_seharian'];
        $baseTotal = fn (Payroll $p) => $coreEarningTotal($p) + $sumOf($p, ['tunjangan_khusus']);
        $info = fn (string $label, \Closure $get, ?string $color = null, bool $text = false) => ['component' => null, 'label' => $label, 'format' => null, 'forceText' => $text, 'editable' => false, 'get' => $get, 'color' => $color];
        $calc = fn (string $label, \Closure $get, ?string $color = null, ?string $format = null) => ['component' => null, 'label' => $label, 'format' => $format ?? $money, 'forceText' => false, 'editable' => false, 'get' => $get, 'color' => $color];
        $edit = fn (string $label, PayrollComponentEnum $c, ?string $color = null) => ['component' => $c, 'label' => $label, 'format' => $money, 'forceText' => false, 'editable' => true, 'get' => fn ($p) => $amountFor($p, $c), 'color' => $color];

        $rsDefinitions = [
            $info('NO', fn ($_, $no) => $no),
            $info('NAMA', fn ($p) => $p->employee?->nama_lengkap),
            $info('PERIODE GAJI', fn ($p) => $p->payrollPeriod?->end_date?->translatedFormat('d/m/Y')),
            $info('TGL PEMBAYARAN GAJI', fn ($p) => $p->payrollPeriod?->payment_date?->translatedFormat('d/m/Y')),
            $info('ID', fn ($p) => $p->employee?->nip, null, true),
            $info('NIK', fn ($p) => $profileOf($p)?->identity?->no_nik, null, true),
            $info('STATUS PERKAWINAN', fn ($p) => $profileOf($p)?->marital_status),
            $info('Tanggal Lahir', fn ($p) => $profileOf($p)?->date_of_birth ? Carbon::parse($profileOf($p)->date_of_birth)->format('d/m/Y') : null),
            $info('JABATAN', fn ($p) => $p->import_meta['jabatan'] ?? $profileOf($p)?->jobPosition?->name),
            $info('STATUS KARYAWAN', fn ($p) => $p->import_meta['status_karyawan'] ?? $profileOf($p)?->employeeType?->name),
            $info('Tanggal Masuk', fn ($p) => $joiningDateOf($p)?->format('d/m/Y')),
            $info('Bulan Kerja', fn ($p) => $this->bulanKerja($p)),
            $edit('Tunjangan Masa Kerja', PayrollComponentEnum::TUNJANGAN_MASA_KERJA, 'F20EC1'),
            $info('KELAS', fn ($p) => $p->import_meta['kelas'] ?? $profileOf($p)?->kelas?->name, 'F20EC1'),
            $calc('Gaji Dasar', fn ($p) => $gajiPokokBase($p)),
            $info('% Kenaikan', fn ($p) => ($pc = $gajiPokokPercent($p)) !== null ? rtrim(rtrim(number_format($pc, 2, ',', ''), '0'), ',') . '%' : null),
            $edit('Gaji Pokok', PayrollComponentEnum::GAJI_POKOK, 'FFFF00'),
            $edit('Tunj. Jabatan Skema Baru', PayrollComponentEnum::TUNJANGAN_JABATAN, 'F20EC1'),
            $edit('Insentif', PayrollComponentEnum::INSENTIF, 'F20EC1'),
            $calc('Tunj. Dokter/Jabatan Khusus', fn ($p) => $sumOf($p, ['tunjangan_khusus'])),
            $calc('TOTAL GAJI SKEMA BARU', $baseTotal, 'F20EC1'),
            $edit('Jasa Pelayanan Baru', PayrollComponentEnum::JASA_PELAYANAN, 'F20EC1'),
            $calc('Jumlah Total Gaji+Jasa Pelayanan (SKEMA GAJI BARU)', fn ($p) => $baseTotal($p) + $sumOf($p, ['jasa_pelayanan']), 'F20EC1'),
            $calc('Tambahan Jasa', fn ($p) => $jasaUnit($p, 'Tambahan Jasa')),
            $edit('LEMBUR (Dihitung Stlh 2 Jam)', PayrollComponentEnum::LEMBUR),
            $edit('EXTRA SHIFT', PayrollComponentEnum::EXTRA_SHIFT),
            $calc('Jasa Supir Ambulance & Perawat (Rujukan Pasien)', fn ($p) => $jasaUnit($p, 'Ambulans & Rujukan Pasien')),
            $calc('Jasa Paket Infus', fn ($p) => $jasaUnit($p, 'Paket Infus')),
            $calc('Jumlah Terima Skema Baru', fn ($p) => (float) $p->gross_salary, 'F20EC1'),

            $edit('POTONGAN BPJS 1%', PayrollComponentEnum::BPJS_KESEHATAN),
            $edit('POTONGAN BPJSTK (JHT 2%)', PayrollComponentEnum::BPJS_KETENAGAKERJAAN),
            $calc('JUMLAH TELAT > 5 MENIT/BULAN', fn ($p) => $qtyOf($p, ['keterlambatan']), 'B8CCE4', self::PLAIN_NUMBER_FORMAT),
            $edit('POTONGAN TELAT >5 MENIT', PayrollComponentEnum::KETERLAMBATAN),
            $edit('POTONGAN IZIN TERLAMBAT MASUK ATAU PULANG CEPAT', PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT, 'B8CCE4'),
            $calc('JMLH (CUTI, IZIN, SAKIT)', fn ($p) => $qtyOf($p, $izinCutiTypes), 'B8CCE4', 'General'),
            $calc('POTONGAN IZIN dan CUTI /HARI', fn ($p) => $sumOf($p, $izinCutiTypes)),
            $edit('POTONGAN KOPERASI', PayrollComponentEnum::KOPERASI, 'B6DDE8'),
            $edit('POTONGAN BON', PayrollComponentEnum::BON, 'FBD4B4'),
            $calc('Jumlah Potongan Hari Efektif Kontrak / Cuti Melahirkan', fn ($p) => $sumOf($p, ['potongan_khusus'])),
            $calc('TOTAL POTONGAN', fn ($p) => (float) $p->total_deductions),
            $calc('TOTAL GAJI YANG DITERIMA SKEMA BARU', fn ($p) => (float) $p->net_salary, 'F20EC1'),

            $info('NO REKENING', fn ($p) => $profileOf($p)?->identity?->bank_account_number, null, true),
            $info('NAMA REKENING', fn ($p) => $profileOf($p)?->identity?->bank_account_name),
            $info('Alamat Email', fn ($p) => $profileOf($p)?->email_pribadi ?: $p->employee?->user?->email),
        ];

        $definitions = $sameLayout ? $rsDefinitions : [
            ['component' => null, 'label' => 'NO', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($_, $no) => $no],
            ['component' => null, 'label' => 'NAMA', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $payroll->employee?->nama_lengkap],
            ['component' => null, 'label' => 'PERIODE GAJI', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $payroll->payrollPeriod?->end_date?->translatedFormat('d/m/Y')],
            ['component' => null, 'label' => 'TGL PEMBAYARAN GAJI', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $payroll->payrollPeriod?->payment_date?->translatedFormat('d/m/Y')],
            ['component' => null, 'label' => 'ID', 'format' => null, 'forceText' => true, 'editable' => false, 'get' => fn ($payroll) => $payroll->employee?->nip],
            ['component' => null, 'label' => 'Tanggal Lahir', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->date_of_birth ? Carbon::parse($profileOf($payroll)->date_of_birth)->format('d/m/Y') : null],
            ['component' => null, 'label' => 'JABATAN', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->jobPosition?->name],
            ['component' => null, 'label' => 'STATUS KARYAWAN', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->employeeType?->name],
            ['component' => null, 'label' => 'Tanggal Masuk', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $joiningDateOf($payroll)?->format('d/m/Y')],
            ['component' => null, 'label' => 'Bulan Kerja', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $this->bulanKerja($payroll)],
            ['component' => null, 'label' => 'KELAS', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->kelas?->name],
            ['component' => null, 'label' => 'PANGKAT', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->pangkat?->name],

            ['component' => PayrollComponentEnum::GAJI_POKOK, 'label' => 'Gaji Dasar', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $gajiPokokBase($payroll)],
            ['component' => PayrollComponentEnum::GAJI_POKOK, 'label' => '% Kenaikan', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => ($p = $gajiPokokPercent($payroll)) !== null ? rtrim(rtrim(number_format($p, 2, ',', ''), '0'), ',') . '%' : null],
            ['component' => PayrollComponentEnum::GAJI_POKOK, 'label' => 'Gaji Pokok', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::GAJI_POKOK)],
            ['component' => PayrollComponentEnum::TUNJANGAN_JABATAN, 'label' => 'Tunjangan Jabatan', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::TUNJANGAN_JABATAN)],
            ['component' => PayrollComponentEnum::TUNJANGAN_TRANSPORTASI, 'label' => 'Tunjangan Transportasi', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::TUNJANGAN_TRANSPORTASI)],
            ['component' => PayrollComponentEnum::TUNJANGAN_MASA_KERJA, 'label' => 'Tunjangan Masa Kerja', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::TUNJANGAN_MASA_KERJA)],
            ['component' => PayrollComponentEnum::INSENTIF, 'label' => 'Insentif', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::INSENTIF)],

            ['component' => null, 'label' => 'TOTAL GAJI', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $coreEarningTotal($payroll)],
            ['component' => null, 'label' => 'LEMBUR', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $otherEarningTotal($payroll)],
            ['component' => null, 'label' => 'Jumlah Terima', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => (float) $payroll->gross_salary],

            ['component' => PayrollComponentEnum::BPJS_KESEHATAN, 'label' => 'POTONGAN BPJS 1%', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::BPJS_KESEHATAN)],
            ['component' => PayrollComponentEnum::BPJS_KETENAGAKERJAAN, 'label' => 'POTONGAN BPJSTK (JHT 2%)', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::BPJS_KETENAGAKERJAAN)],
            ['component' => PayrollComponentEnum::KETERLAMBATAN, 'label' => 'JUMLAH TELAT > 5 MENIT/BULAN', 'format' => self::PLAIN_NUMBER_FORMAT, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $quantityFor($payroll, PayrollComponentEnum::KETERLAMBATAN)],
            ['component' => PayrollComponentEnum::KETERLAMBATAN, 'label' => 'POTONGAN TELAT >5 MENIT', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::KETERLAMBATAN)],
            ['component' => PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT, 'label' => 'JUMLAH IZIN TERLAMBAT MASUK ATAU PULANG CEPAT', 'format' => self::PLAIN_NUMBER_FORMAT, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $quantityFor($payroll, PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT)],
            ['component' => PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT, 'label' => 'POTONGAN IZIN TERLAMBAT MASUK ATAU PULANG CEPAT', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT)],
            ['component' => PayrollComponentEnum::CUTI, 'label' => 'JUMLAH CUTI', 'format' => self::PLAIN_NUMBER_FORMAT, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $quantityFor($payroll, PayrollComponentEnum::CUTI)],
            ['component' => PayrollComponentEnum::CUTI, 'label' => 'POTONGAN CUTI/HARI', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::CUTI)],
            // General (bukan PLAIN_NUMBER_FORMAT '0') - izin seharian bisa pecahan (0.5 hari
            // buat setengah hari), format '0' bikin nilainya kelihatan dibulatkan (2026-08-26).
            ['component' => PayrollComponentEnum::IZIN_SEHARIAN, 'label' => 'JUMLAH IZIN SEHARIAN', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $quantityFor($payroll, PayrollComponentEnum::IZIN_SEHARIAN)],
            ['component' => PayrollComponentEnum::IZIN_SEHARIAN, 'label' => 'POTONGAN IZIN/HARI', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::IZIN_SEHARIAN)],
            ['component' => PayrollComponentEnum::KOPERASI, 'label' => 'POTONGAN KOPERASI', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::KOPERASI)],
            ['component' => PayrollComponentEnum::BON, 'label' => 'POTONGAN BON', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::BON)],

            ['component' => null, 'label' => 'TOTAL POTONGAN', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => (float) $payroll->total_deductions],
            ['component' => null, 'label' => 'TOTAL GAJI YANG DITERIMA', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => (float) $payroll->net_salary],

            ['component' => null, 'label' => 'NIK', 'format' => null, 'forceText' => true, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->identity?->no_nik],
            ['component' => null, 'label' => 'STATUS PERKAWINAN', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->marital_status],
            ['component' => null, 'label' => 'NO REKENING', 'format' => null, 'forceText' => true, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->identity?->bank_account_number],
            ['component' => null, 'label' => 'NAMA REKENING', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->identity?->bank_account_name],
            ['component' => null, 'label' => 'Alamat Email', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->email_pribadi ?: $payroll->employee?->user?->email],
        ];

        return array_values(array_filter($definitions, fn (array $d) => $isActive($d['component'])));
    }
}
