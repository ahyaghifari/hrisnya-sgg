<?php

namespace App\Exports\Attendance;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceHistoryExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    /**
     * @param  array<int, array{date: string, type: string, keterangan: string, status: string}>  $rows
     */
    public function __construct(
        protected array $rows,
        protected string $employeeName,
        protected string $periodLabel,
    ) {}

    public function title(): string
    {
        return 'Riwayat Absensi';
    }

    public function headings(): array
    {
        return ['Tanggal Pengajuan', 'Jenis', 'Keterangan', 'Status'];
    }

    public function array(): array
    {
        return array_map(fn ($row) => [
            $row['date'],
            $row['type'],
            $row['keterangan'],
            $row['status'],
        ], $this->rows);
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 14, 'C' => 35, 'D' => 16];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = 'D';
        $lastRow = count($this->rows) + 2;

        $sheet->insertNewRowBefore(1);
        $sheet->setCellValue('A1', "Riwayat Absensi — {$this->employeeName}");
        $sheet->setCellValue('B1', "Periode: {$this->periodLabel}");
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '042c6c']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563eb']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);

        $sheet->getStyle("A3:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        for ($row = 3; $row <= $lastRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                ]);
            }
        }

        return [];
    }
}
