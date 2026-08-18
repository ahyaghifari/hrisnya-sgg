<?php

namespace App\Exports\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Payroll\CompanyPayrollComponent;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use App\Models\Setting;
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
 * (knowledge/Gaji Juni 2026 SGG.xlsx) - dikelompokkan per DIVISI (org level 2, lihat
 * UserProfiles::getDivisionAttribute()) dengan baris judul divisi disisipkan di antara baris
 * karyawan (kolom lain di baris judul dibiarkan kosong, bukan di-merge - cukup buat dibaca
 * finance, tidak perlu identik piksel).
 *
 * Kolom komponen gaji (Gaji Pokok..Bon) HANYA muncul kalau komponennya aktif di
 * `company_payroll_components` untuk company periode ini - kolom identitas + ringkasan
 * (TOTAL GAJI/Jumlah Terima/TOTAL POTONGAN/dst) selalu tampil apa pun toggle-nya.
 * buildColumns() jadi SATU-SATUNYA sumber urutan+tipe kolom, dipakai bareng headings(),
 * payrollRow(), dan styling (currency/text) di registerEvents() - tidak akan pernah desinkron.
 *
 * SENGAJA cuma baca dari payroll_items (Payroll::items, sudah termasuk baris yang berasal dari
 * payroll_manual_lines - lihat AbstractManualLineCalculator yang menyalin Koperasi/Bon manual
 * ke payroll_items saat generate) - bukan query ulang payroll_manual_lines. Pengecualian:
 * "Gaji Dasar"/"% Kenaikan" (base & langkah kenaikan kelas Gaji Pokok) TIDAK tersimpan
 * terpisah di payroll_items (cuma nominal akhir) - dihitung ulang dari master data
 * (pangkat_salaries/kelas/setting) persis rumus GajiPokokCalculator, TANPA prorate bulan
 * pertama (kolom ini buat referensi basis perhitungan, bukan angka final - "Gaji Pokok" di
 * sebelahnya tetap angka final asli dari payroll_items).
 */
class PayrollSlipRekapExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithStyles, WithCustomValueBinder, WithEvents
{
    private const CORE_EARNINGS = [
        PayrollComponentEnum::GAJI_POKOK,
        PayrollComponentEnum::TUNJANGAN_JABATAN,
        PayrollComponentEnum::TUNJANGAN_TRANSPORTASI,
        PayrollComponentEnum::TUNJANGAN_MASA_KERJA,
        PayrollComponentEnum::INSENTIF,
    ];

    private const CURRENCY_FORMAT = '"Rp" #,##0';

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
        $grouped = $this->payrolls
            ->groupBy(fn (Payroll $payroll) => $payroll->user?->profile?->division?->name ?? 'Tanpa Divisi')
            ->sortKeys();

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

    /** Warna & wrap header ikut identitas modul Keuangan di sistem (--brand-success, lihat
     *  .nav-item-green di resources/css/app.css) - bukan warna bebas pilihan sendiri. */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '047C54']],
                'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
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
                    if (mb_strlen($column['label']) > 20) {
                        $sheet->getColumnDimension($letter)->setAutoSize(false)->setWidth(18);
                    } else {
                        $sheet->getColumnDimension($letter)->setAutoSize(true);
                    }

                    if ($column['format']) {
                        $sheet->getStyle("{$letter}2:{$letter}{$lastRow}")->getNumberFormat()->setFormatCode($column['format']);
                    }
                }

                $sheet->getRowDimension(1)->setRowHeight(45);

                foreach ($this->groupHeaderRows as $rowNumber) {
                    $sheet->getStyle("A{$rowNumber}:" . Coordinate::stringFromColumnIndex(count($this->columns)) . $rowNumber)
                        ->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                        ]);
                }

                $lastColumn = Coordinate::stringFromColumnIndex(count($this->columns));
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
                ]);

                $sheet->freezePane('A2');
            },
        ];
    }

    private function groupHeaderRow(string $name): array
    {
        return array_pad(['', $name], count($this->columns), '');
    }

    /**
     * Urutan dalam 1 divisi: pangkat paling tinggi dulu (Pangkats::level, makin besar makin
     * tinggi jenjang - konvensi sama dengan getPangkatOptionsProperty()), lalu bulan kerja
     * paling lama, baru nama - semuanya diminta user. Karyawan tanpa pangkat/tanggal masuk
     * ditaruh paling belakang di kriteria itu (bukan dianggap "tertinggi"/"terlama").
     */
    private function sortWithinDivision(Collection $payrolls): Collection
    {
        return $payrolls->sort(function (Payroll $a, Payroll $b) {
            $levelCompare = ($this->pangkatLevel($b) ?? -INF) <=> ($this->pangkatLevel($a) ?? -INF);
            if ($levelCompare !== 0) {
                return $levelCompare;
            }

            $tenureCompare = ($this->bulanKerja($b) ?? -INF) <=> ($this->bulanKerja($a) ?? -INF);
            if ($tenureCompare !== 0) {
                return $tenureCompare;
            }

            return strcmp((string) $a->user?->name, (string) $b->user?->name);
        })->values();
    }

    private function pangkatLevel(Payroll $payroll): ?int
    {
        return $payroll->user?->profile?->pangkat?->level;
    }

    /** Digenapkan ke bulan penuh (floor, bukan desimal) - permintaan user, jangan tampil koma. */
    private function bulanKerja(Payroll $payroll): ?int
    {
        $joiningDate = $payroll->user?->profile?->joining_date;

        return $joiningDate ? (int) floor(Carbon::parse($joiningDate)->diffInMonths(now())) : null;
    }

    private function payrollRow(Payroll $payroll, int $no): array
    {
        return array_map(fn (array $column) => $column['get']($payroll, $no), $this->columns);
    }

    /**
     * Komponen yang case-nya "alwaysOn()" (FEE_CLAIM) tidak butuh baris `company_payroll_components`
     * buat aktif (lihat PayrollComponentEnum::alwaysOn()) - diperlakukan aktif juga di sini.
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
     * Base gaji (sebelum kenaikan kelas) & persen kenaikan kumulatif - rumus sama persis
     * GajiPokokCalculator (additive, bukan compound), TANPA prorate. Di-memoize per Payroll
     * (spl_object_id) supaya relasi pangkat->pangkatSalary/startKelas/maxKelas cuma ditelusuri
     * sekali walau dipakai 2 kolom (Gaji Dasar & % Kenaikan).
     *
     * @return array{0: ?float, 1: ?float} [base, percentKenaikan]
     */
    private function gajiPokokBreakdown(Payroll $payroll, array &$cache): array
    {
        $key = spl_object_id($payroll);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $profile = $payroll->user?->profile;
        $pangkat = $profile?->pangkat;
        $kelas = $profile?->kelas;
        $pangkatSalary = $pangkat?->pangkatSalary;

        if (! $pangkat || ! $kelas || ! $pangkatSalary) {
            return $cache[$key] = [null, null];
        }

        $startLevel = $pangkat->startKelas?->level;
        if ($kelas->level === null || $startLevel === null) {
            return $cache[$key] = [(float) $pangkatSalary->base_salary, null];
        }

        $steps = max(0, $kelas->level - $startLevel);
        $maxLevel = $pangkat->maxKelas?->level;
        if ($maxLevel !== null) {
            $steps = min($steps, max(0, $maxLevel - $startLevel));
        }

        $rate = (float) Setting::resolve('gaji_pokok_kenaikan_per_kelas', $payroll->payrollPeriod?->company_id, default: 0);

        return $cache[$key] = [(float) $pangkatSalary->base_salary, $rate * $steps];
    }

    /** @return array<int, array{component: ?PayrollComponentEnum, label: string, get: \Closure, format: ?string, forceText: bool}> */
    private function buildColumns(): array
    {
        $active = $this->activeComponentValues();
        $isActive = fn (?PayrollComponentEnum $component) => $component === null || in_array($component->value, $active, true);

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

        $profileOf = fn (Payroll $payroll) => $payroll->user?->profile;
        $joiningDateOf = function (Payroll $payroll) use ($profileOf) {
            $joiningDate = $profileOf($payroll)?->joining_date;

            return $joiningDate ? Carbon::parse($joiningDate) : null;
        };

        $breakdownCache = [];
        $gajiPokokBase = fn (Payroll $payroll) => $this->gajiPokokBreakdown($payroll, $breakdownCache)[0];
        $gajiPokokPercent = fn (Payroll $payroll) => $this->gajiPokokBreakdown($payroll, $breakdownCache)[1];

        $definitions = [
            ['component' => null, 'label' => 'NO', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($_, $no) => $no],
            ['component' => null, 'label' => 'NAMA', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $payroll->user?->name],
            ['component' => null, 'label' => 'PERIODE GAJI', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $payroll->payrollPeriod?->end_date?->translatedFormat('d/m/Y')],
            ['component' => null, 'label' => 'TGL PEMBAYARAN GAJI', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $payroll->payrollPeriod?->payment_date?->translatedFormat('d/m/Y')],
            ['component' => null, 'label' => 'ID', 'format' => null, 'forceText' => true, 'editable' => false, 'get' => fn ($payroll) => $payroll->user?->nip],
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
            ['component' => PayrollComponentEnum::KETERLAMBATAN, 'label' => 'JUMLAH TELAT > 5 MENIT/BULAN', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $quantityFor($payroll, PayrollComponentEnum::KETERLAMBATAN)],
            ['component' => PayrollComponentEnum::KETERLAMBATAN, 'label' => 'POTONGAN TELAT >5 MENIT', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::KETERLAMBATAN)],
            ['component' => PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT, 'label' => 'JUMLAH IZIN TERLAMBAT MASUK ATAU PULANG CEPAT', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $quantityFor($payroll, PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT)],
            ['component' => PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT, 'label' => 'POTONGAN IZIN TERLAMBAT MASUK ATAU PULANG CEPAT', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT)],
            ['component' => PayrollComponentEnum::CUTI, 'label' => 'JUMLAH CUTI', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $quantityFor($payroll, PayrollComponentEnum::CUTI)],
            ['component' => PayrollComponentEnum::CUTI, 'label' => 'POTONGAN CUTI/HARI', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::CUTI)],
            ['component' => PayrollComponentEnum::IZIN_SEHARIAN, 'label' => 'JUMLAH IZIN SEHARIAN', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $quantityFor($payroll, PayrollComponentEnum::IZIN_SEHARIAN)],
            ['component' => PayrollComponentEnum::IZIN_SEHARIAN, 'label' => 'POTONGAN IZIN/HARI', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::IZIN_SEHARIAN)],
            ['component' => PayrollComponentEnum::KOPERASI, 'label' => 'POTONGAN KOPERASI', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::KOPERASI)],
            ['component' => PayrollComponentEnum::BON, 'label' => 'POTONGAN BON', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => true, 'get' => fn ($payroll) => $amountFor($payroll, PayrollComponentEnum::BON)],

            ['component' => null, 'label' => 'TOTAL POTONGAN', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => (float) $payroll->total_deductions],
            ['component' => null, 'label' => 'TOTAL GAJI YANG DITERIMA', 'format' => self::CURRENCY_FORMAT, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => (float) $payroll->net_salary],

            ['component' => null, 'label' => 'NIK', 'format' => null, 'forceText' => true, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->no_nik],
            ['component' => null, 'label' => 'STATUS PERKAWINAN', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->marital_status],
            ['component' => null, 'label' => 'NO REKENING', 'format' => null, 'forceText' => true, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->bank_account_number],
            ['component' => null, 'label' => 'NAMA REKENING', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->bank_account_name],
            ['component' => null, 'label' => 'Alamat Email', 'format' => null, 'forceText' => false, 'editable' => false, 'get' => fn ($payroll) => $profileOf($payroll)?->email_pribadi ?: $payroll->user?->email],
        ];

        return array_values(array_filter($definitions, fn (array $d) => $isActive($d['component'])));
    }
}
