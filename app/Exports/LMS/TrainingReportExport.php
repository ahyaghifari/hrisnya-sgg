<?php

namespace App\Exports\LMS;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TrainingReportExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        protected array  $matrix,
        protected array  $courseHeaders, // ['id' => title, ...]
        protected string $baselinePeriod,
        protected string $companyName,
    ) {}

    public function title(): string
    {
        return 'Laporan Pelatihan';
    }

    public function headings(): array
    {
        $headers = ['NIP', 'Nama Karyawan', 'Perusahaan'];

        foreach ($this->courseHeaders as $title) {
            $headers[] = $title;
        }

        $headers[] = 'Jam Belum Terpenuhi';
        $headers[] = 'Total Jam Pelatihan';
        $headers[] = 'Total Pelatihan Diikuti';

        return $headers;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->matrix as $row) {
            $line = [
                $row['nip'],
                $row['name'],
                $row['company'],
            ];

            foreach (array_keys($this->courseHeaders) as $courseId) {
                $line[] = ($row['courses'][$courseId] ?? false) ? '✓' : '✗';
            }

            $line[] = $row['unfulfilled_hours'] > 0
                ? number_format($row['unfulfilled_hours'], 1) . ' jam'
                : 'Terpenuhi';
            $line[] = number_format($row['total_hours'], 1) . ' jam';
            $line[] = $row['total_courses'];

            $rows[] = $line;
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 18, // NIP
            'B' => 30, // Nama
            'C' => 25, // Perusahaan
        ];

        $col = 4; // D onwards for courses
        foreach ($this->courseHeaders as $title) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $widths[$colLetter] = max(18, min(30, strlen($title)));
            $col++;
        }

        // Last 3 summary columns
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $widths[$colLetter] = 22;
        $widths[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1)] = 20;
        $widths[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 2)] = 22;

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $totalCols  = 3 + count($this->courseHeaders) + 3;
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
        $lastRow    = count($this->matrix) + 2; // +1 for info row, +1 for heading row

        // Insert info row before headings
        $sheet->insertNewRowBefore(1);
        $sheet->setCellValue('A1', 'Laporan Pelatihan — ' . $this->companyName);
        $sheet->setCellValue('B1', 'Periode: ' . $this->baselinePeriod);
        $sheet->mergeCells('A1:' . $lastColLetter . '1');

        $sheet->getStyle('A1:' . $lastColLetter . '1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Heading row (now row 2 after insert)
        $headingRange = 'A2:' . $lastColLetter . '2';
        $sheet->getStyle($headingRange)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563eb']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']],
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(36);

        // Data rows
        $dataRange = 'A3:' . $lastColLetter . ($lastRow + 1);
        $sheet->getStyle($dataRange)->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
            'borders'   => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);

        // Center course checkmark columns
        $startCourseColIdx = 4;
        $endCourseColIdx   = 3 + count($this->courseHeaders);
        if ($endCourseColIdx >= $startCourseColIdx) {
            $courseRange = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startCourseColIdx)
                . '3:'
                . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($endCourseColIdx)
                . ($lastRow + 1);
            $sheet->getStyle($courseRange)->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font'      => ['size' => 13, 'color' => ['rgb' => '16A34A']],
            ]);
        }

        // Alternate row colors for readability
        for ($row = 3; $row <= $lastRow + 1; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':' . $lastColLetter . $row)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                ]);
            }
        }

        // Summary columns (last 3) center-aligned
        $sumStart = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($endCourseColIdx + 1);
        $sumEnd   = $lastColLetter;
        $sheet->getStyle($sumStart . '2:' . $sumEnd . ($lastRow + 1))->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Freeze top 2 rows + first column
        $sheet->freezePane('D3');

        return [];
    }
}
