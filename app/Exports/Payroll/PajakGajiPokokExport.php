<?php

namespace App\Exports\Payroll;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Exports\Payroll\Concerns\GroupsPayrollsByDivision;
use App\Models\Payroll\Payroll;
use App\Services\Payroll\GajiPokokBreakdownResolver;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Halaman Pajak (%) — potongan 3 kolom Gaji Pokok dari PayrollSlipRekapExport (Gaji Dasar, %
 * Kenaikan, Gaji Pokok final), TANPA seluruh kolom komponen lain - finance cuma butuh basis
 * perhitungan PPh21, bukan rekap gaji lengkap. Grouping per DIVISI + fallback Presiden
 * Direktur/Manajemen SAMA PERSIS PayrollSlipRekapExport (GroupsPayrollsByDivision, 2026-08-22
 * instruksi user: "disesuaikan seperti payroll rekap export"), font/style juga ikut (Times New
 * Roman 11) biar konsisten satu modul.
 */
class PajakGajiPokokExport implements FromCollection, WithHeadings, WithStyles, WithEvents
{
    use GroupsPayrollsByDivision;

    private const CURRENCY_FORMAT = '"Rp" #,##0';

    /** Baris (1-based, sudah termasuk offset heading) yang berisi judul divisi - buat styling di registerEvents(). */
    private array $groupHeaderRows = [];

    /** @param Collection<int, Payroll> $payrolls */
    public function __construct(protected Collection $payrolls, protected string $companyId) {}

    public function collection(): Collection
    {
        $grouped = $this->groupedByDivision($this->payrolls, $this->companyId);

        $rows = collect();
        $rowNumber = 1; // baris 1 = heading, data mulai baris 2
        $breakdownCache = [];

        foreach ($grouped as $divisionName => $payrolls) {
            $rowNumber++;
            $this->groupHeaderRows[] = $rowNumber;
            $rows->push(['', $divisionName, '', '', '', '', '', '']);

            $no = 1; // reset per divisi, sama pola PayrollSlipRekapExport.
            foreach ($this->sortWithinDivision($payrolls) as $payroll) {
                $rowNumber++;
                $rows->push($this->payrollRow($payroll, $no, $breakdownCache));
                $no++;
            }
        }

        return $rows;
    }

    private function payrollRow(Payroll $payroll, int $no, array &$breakdownCache): array
    {
        [$base, $percent] = $breakdownCache[$payroll->id] ??= GajiPokokBreakdownResolver::resolve($payroll);

        $gajiPokokItem = $payroll->items->firstWhere('source_type', PayrollComponentEnum::GAJI_POKOK->value);
        $profile = $payroll->user?->profile;

        return [
            $no,
            $payroll->user?->name,
            $profile?->jobPosition?->name,
            $profile?->kelas?->name,
            $profile?->pangkat?->name,
            $base,
            $percent !== null ? rtrim(rtrim(number_format($percent, 2, ',', ''), '0'), ',') . '%' : null,
            (float) ($gajiPokokItem?->amount ?? 0),
        ];
    }

    public function headings(): array
    {
        return ['NO', 'NAMA', 'JABATAN', 'KELAS', 'PANGKAT', 'Gaji Dasar', '% Kenaikan', 'Gaji Pokok'];
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

                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $letter) {
                    // PANGKAT (E) nama kadang panjang - dilebarkan & wrap, sama pola PayrollSlipRekapExport.
                    if ($letter === 'E') {
                        $sheet->getColumnDimension($letter)->setAutoSize(false)->setWidth(24);
                        $sheet->getStyle("{$letter}2:{$letter}{$lastRow}")->getAlignment()->setWrapText(true);
                    } else {
                        $sheet->getColumnDimension($letter)->setAutoSize(true);
                    }
                }

                $sheet->getStyle('F2:F' . $lastRow)->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
                $sheet->getStyle('H2:H' . $lastRow)->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);

                foreach ($this->groupHeaderRows as $rowNumber) {
                    $sheet->getStyle("A{$rowNumber}:H{$rowNumber}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                    ]);
                }

                $sheet->freezePane('C2');
            },
        ];
    }
}
