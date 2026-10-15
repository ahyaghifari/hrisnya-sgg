<?php

namespace App\Exports\Presensi;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Rekap Lembur: 1 baris per tanggal lembur, kolom No/Nama/Jabatan/Unit di-merge
 * vertikal per karyawan. Input = $recap['rows'] dari LemburRecapPage::getRecapProperty().
 */
class LemburRecapExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    /** @var array<int, array{0: int, 1: int}> baris excel (awal, akhir) tiap karyawan, untuk merge */
    protected array $spans = [];

    public function __construct(protected array $rows) {}

    public function title(): string
    {
        return 'Rekap Lembur';
    }

    public function headings(): array
    {
        return ['No', 'Nama', 'Jabatan', 'Unit', 'Tanggal', 'Tugas', 'Waktu', 'Total Jam', 'Presensi'];
    }

    public function array(): array
    {
        $out = [];
        $this->spans = [];
        $excelRow = 2; // baris 1 = heading

        foreach ($this->rows as $i => $row) {
            $p = $row['user']->profile;
            $unit = $p?->division?->name ?? $p?->department?->name;
            $start = $excelRow;

            foreach ($row['items'] as $k => $item) {
                $hours = rtrim(rtrim(number_format($item['total_hours'], 1), '0'), '.');
                $out[] = [
                    $k === 0 ? $i + 1 : '',
                    $k === 0 ? $row['user']->name : '',
                    $k === 0 ? $p?->jobPosition?->name : '',
                    $k === 0 ? $unit : '',
                    Carbon::parse($item['date'])->format('d-m-Y'),
                    mb_strtoupper($item['type_name']),
                    str_replace(':', '.', substr($item['start_time'], 0, 5)) . ' - ' . str_replace(':', '.', substr($item['end_time'], 0, 5)),
                    $hours . ' JAM',
                    $item['presensi']
                        ? str_replace(':', '.', substr($item['presensi'][0], 0, 5)) . ' - ' . ($item['presensi'][1] ? str_replace(':', '.', substr($item['presensi'][1], 0, 5)) : '?')
                        : 'Belum presensi',
                ];
                $excelRow++;
            }

            $this->spans[] = [$start, $excelRow - 1];
        }

        return $out;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 30, 'C' => 28, 'D' => 28, 'E' => 14, 'F' => 20, 'G' => 18, 'H' => 12, 'I' => 18];
    }

    public function styles(Worksheet $sheet): array
    {
        $last = max(count($this->array()) + 1, 2);

        foreach ($this->spans as [$a, $b]) {
            if ($b > $a) {
                foreach (['A', 'B', 'C', 'D'] as $col) {
                    $sheet->mergeCells("{$col}{$a}:{$col}{$b}");
                }
            }
        }

        $sheet->getStyle("A1:I1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '042c6c']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A1:I{$last}")->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);
        $sheet->getStyle("A2:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }
}
