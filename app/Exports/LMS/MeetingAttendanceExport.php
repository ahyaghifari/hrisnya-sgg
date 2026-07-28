<?php

namespace App\Exports\LMS;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MeetingAttendanceExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    /**
     * @param  array<int, array{name: string, masuk: bool, pulang: bool, keterangan: ?string}>  $rows
     */
    public function __construct(
        protected array $rows,
        protected string $courseTitle,
        protected string $meetingTitle,
        protected ?string $meetingDate,
    ) {}

    public function title(): string
    {
        return 'Absensi Pertemuan';
    }

    public function headings(): array
    {
        return ['Nama Peserta', 'Absen Masuk', 'Absen Pulang', 'Keterangan Kehadiran'];
    }

    public function array(): array
    {
        return array_map(fn ($row) => [
            $row['name'],
            $row['masuk'] ? 'Hadir' : 'Tidak Hadir',
            $row['pulang'] ? 'Hadir' : 'Tidak Hadir',
            $row['keterangan'] ?: '-',
        ], $this->rows);
    }

    public function columnWidths(): array
    {
        return ['A' => 30, 'B' => 16, 'C' => 16, 'D' => 35];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = 'D';
        $lastRow = count($this->rows) + 2;

        $sheet->insertNewRowBefore(1);
        $sheet->setCellValue('A1', "Absensi Pertemuan — {$this->courseTitle} ({$this->meetingTitle})");
        $sheet->setCellValue('B1', $this->meetingDate ? 'Tanggal: ' . $this->meetingDate : '');
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
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        $sheet->getStyle("B3:C{$lastRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
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
