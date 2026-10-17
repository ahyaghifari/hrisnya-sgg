<?php

namespace App\Exports\Payroll;

use App\Enum\Attendance\PermissionGroupEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Exports\Payroll\Concerns\GroupsPayrollsByDivision;
use App\Models\Payroll\Payroll;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Halaman Pajak (%) — kolom identitas + Gaji Pokok final buat basis perhitungan PPh21
 * (2026-08-26, instruksi user: NAMA/JABATAN/STATUS KARYAWAN/GAJI POKOK/NIK/STATUS PERKAWINAN
 * - sebelumnya breakdown Gaji Dasar/% Kenaikan/Kelas/Pangkat, sekarang dilepas karena finance
 * cuma butuh angka final + identitas pajak). Grouping per DIVISI + fallback Presiden
 * Direktur/Manajemen SAMA PERSIS PayrollSlipRekapExport (GroupsPayrollsByDivision), font/style
 * juga ikut (Times New Roman 11) biar konsisten satu modul.
 */
class PajakGajiPokokExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithStyles, WithCustomValueBinder, WithEvents
{
    use GroupsPayrollsByDivision;

    private const CURRENCY_FORMAT = '"Rp" #,##0';

    /** Kolom NIK (F) dipaksa TYPE_STRING biar nol di depan gak ilang - sama alasan
     * WithCustomValueBinder di PayrollSlipRekapExport. */
    private const FORCE_TEXT_COLUMNS = ['F'];

    /** Baris (1-based, sudah termasuk offset heading) yang berisi judul divisi - buat styling di registerEvents(). */
    private array $groupHeaderRows = [];

    /** @param Collection<int, Payroll> $payrolls */
    public function __construct(protected Collection $payrolls, protected string $companyId) {}

    /** Banjarbaru & Barabai dapat 2 kolom tambahan (Tunj. Jabatan, Tunj. Masa Kerja). ID company sama
     * di prod & dev; reuse daftar 2 company dari PermissionGroupEnum (HasCompanyLabelOverride). */
    private function hasExtraColumns(): bool
    {
        return PermissionGroupEnum::usesLabelOverride($this->companyId);
    }

    public function collection(): Collection
    {
        $grouped = $this->groupedByDivision($this->payrolls, $this->companyId);

        $rows = collect();
        $rowNumber = 1; // baris 1 = heading, data mulai baris 2

        foreach ($grouped as $divisionName => $payrolls) {
            $rowNumber++;
            $this->groupHeaderRows[] = $rowNumber;
            $rows->push(array_pad(['', $divisionName], count($this->headings()), ''));

            $no = 1; // reset per divisi, sama pola PayrollSlipRekapExport.
            foreach ($this->sortWithinDivision($payrolls) as $payroll) {
                $rowNumber++;
                $rows->push($this->payrollRow($payroll, $no));
                $no++;
            }
        }

        return $rows;
    }

    private function payrollRow(Payroll $payroll, int $no): array
    {
        $gajiPokokItem = $payroll->items->firstWhere('source_type', PayrollComponentEnum::GAJI_POKOK->value);
        $employee = $payroll->employee;

        $row = [
            $no,
            $payroll->employee?->nama_lengkap,
            $employee?->jobPosition?->name,
            $employee?->employeeType?->name,
            (float) ($gajiPokokItem?->amount ?? 0),
            $employee?->identity?->no_nik,
            $employee?->marital_status,
        ];

        if ($this->hasExtraColumns()) {
            foreach ([PayrollComponentEnum::TUNJANGAN_JABATAN, PayrollComponentEnum::TUNJANGAN_MASA_KERJA] as $component) {
                $row[] = (float) ($payroll->items->firstWhere('source_type', $component->value)?->amount ?? 0);
            }
        }

        return $row;
    }

    public function headings(): array
    {
        $headings = ['NO', 'NAMA', 'JABATAN', 'STATUS KARYAWAN', 'GAJI POKOK', 'NIK', 'STATUS PERKAWINAN'];

        return $this->hasExtraColumns() ? [...$headings, 'TUNJANGAN JABATAN', 'TUNJANGAN MASA KERJA'] : $headings;
    }

    /** Sama alasan PayrollSlipRekapExport::bindValue() - NIK yang keliatan numerik (mis.
     * "00123") kehilangan nol di depan kalau cuma diformat '@' SETELAH ditulis sebagai angka. */
    public function bindValue(Cell $cell, $value)
    {
        $column = $cell->getColumn();

        if (in_array($column, self::FORCE_TEXT_COLUMNS, true) && $value !== null && $value !== '') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(11);

        return [
            1 => [
                'font' => ['bold' => true],
                'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $last = $this->hasExtraColumns() ? 'I' : 'G';
                foreach (range('A', $last) as $letter) {
                    $sheet->getColumnDimension($letter)->setAutoSize(true);
                }

                $sheet->getStyle('E2:E' . $lastRow)->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
                if ($this->hasExtraColumns()) {
                    $sheet->getStyle('H2:I' . $lastRow)->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
                }

                foreach ($this->groupHeaderRows as $rowNumber) {
                    $sheet->getStyle("A{$rowNumber}:{$last}{$rowNumber}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                    ]);
                }

                $sheet->freezePane('C2');
            },
        ];
    }
}
