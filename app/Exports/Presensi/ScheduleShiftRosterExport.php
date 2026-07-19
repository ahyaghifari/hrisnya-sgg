<?php

namespace App\Exports\Presensi;

use App\Models\Shift\Schedule;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export grid tab "Detail Penjadwalan" — 1 baris = 1 karyawan, kolom tanggal 1..akhir bulan,
 * isi kode shift/"L" (Libur) diwarnai persis sama seperti tampilan di tab (warna & warna font
 * dari ScheduleShiftType, bukan dihitung ulang) — "L" pakai warna pudar yang sama juga.
 */
class ScheduleShiftRosterExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    /**
     * @param  Collection<int, \App\Models\User>  $employees
     * @param  array<int, \Carbon\Carbon>  $dates
     * @param  array<string, array<string, string>>  $roster  [user_id => [date => shift_type_id|'off']]
     */
    public function __construct(
        protected Schedule $schedule,
        protected Collection $employees,
        protected array $dates,
        protected array $roster,
        protected string $periodLabel,
    ) {}

    public function title(): string
    {
        return 'Detail Penjadwalan';
    }

    public function array(): array
    {
        $rows = [];

        $header = ['Karyawan'];
        foreach ($this->dates as $date) {
            $header[] = $date->format('d');
        }
        $rows[] = $header;

        foreach ($this->employees as $employee) {
            $userRoster = $this->roster[$employee->id] ?? [];
            $row = [$employee->name];

            foreach ($this->dates as $date) {
                $value = $userRoster[$date->toDateString()] ?? '';
                $row[] = $value === 'off' ? 'L' : ($this->schedule->shiftTypes->firstWhere('id', $value)?->code ?? '');
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 22];

        foreach ($this->dates as $i => $date) {
            $widths[Coordinate::stringFromColumnIndex($i + 2)] = 4.5;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = Coordinate::stringFromColumnIndex(count($this->dates) + 1);
        $dataLastRow = count($this->employees) + 3;

        // Baris judul + keterangan disisipkan di atas grid ("Karyawan" + tanggal) yang datang
        // dari array() — sama pola dengan AttendanceHistoryExport.
        $sheet->insertNewRowBefore(1, 2);
        $sheet->setCellValue('A1', "Detail Penjadwalan — {$this->schedule->name}");
        $sheet->setCellValue('A2', "Periode: {$this->periodLabel} | Keterangan: {$this->legendText()}");
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '042c6c']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'font' => ['italic' => true, 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(24);

        $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563eb']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);

        $sheet->getStyle("A3:{$lastCol}{$dataLastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A4:A{$dataLastRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            'font' => ['bold' => true],
        ]);

        foreach ($this->employees as $ri => $employee) {
            $userRoster = $this->roster[$employee->id] ?? [];
            $excelRow = 4 + $ri;

            foreach ($this->dates as $ci => $date) {
                $value = $userRoster[$date->toDateString()] ?? '';
                $cell = Coordinate::stringFromColumnIndex($ci + 2).$excelRow;

                if ($value === 'off') {
                    $sheet->getStyle($cell)->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EC8888']],
                        'font' => ['color' => ['rgb' => '7F1D1D'], 'bold' => true],
                    ]);

                    continue;
                }

                $shiftType = $this->schedule->shiftTypes->firstWhere('id', $value);

                if ($shiftType?->color) {
                    $sheet->getStyle($cell)->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ltrim($shiftType->color, '#')]],
                        'font' => ['color' => ['rgb' => ltrim($shiftType->font_color ?: '#000000', '#')], 'bold' => true],
                    ]);
                }
            }
        }

        return [];
    }

    private function legendText(): string
    {
        $parts = $this->schedule->shiftTypes->map(fn ($t) => "{$t->code}={$t->name}")->all();
        $parts[] = 'L=Libur';

        return implode(', ', $parts);
    }
}
