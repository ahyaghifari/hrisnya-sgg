<?php

namespace App\Exports\Presensi;

use Closure;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export rincian rekap presensi (lembur/izin/cuti/tukar shift/extra shift): 1 baris per entri,
 * kolom No/Nama/Jabatan/Unit di-merge vertikal per karyawan. Input = $recap['rows'] dari
 * halaman rekap; kolom entri (setelah Unit) ditentukan caller lewat $detailHeadings + $detail.
 */
class RecapDetailExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    /** @var array<int, array{0: int, 1: int}> baris excel (awal, akhir) tiap karyawan, untuk merge */
    protected array $spans = [];

    /**
     * @param  array<int, string>  $detailHeadings
     * @param  Closure(array): array  $detail  item rekap -> sel-sel kolom entri (urutan = $detailHeadings)
     */
    public function __construct(
        protected array $rows,
        protected array $detailHeadings,
        protected Closure $detail,
        protected string $title = 'Rekap',
    ) {}

    public static function date($value): string
    {
        return $value ? Carbon::parse($value)->format('d-m-Y') : '-';
    }

    /** "08:00:00" -> "08.00" */
    public static function time($value): string
    {
        return $value ? str_replace(':', '.', substr((string) $value, 0, 5)) : '-';
    }

    /** Presensi [masuk, pulang] -> "08.00 - 17.00"; null = "Belum presensi". */
    public static function presensi($in, $out = null): string
    {
        return $in ? self::time($in) . ' - ' . ($out ? self::time($out) : '?') : 'Belum presensi';
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return array_merge(['No', 'Nama', 'Jabatan', 'Unit'], $this->detailHeadings);
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
                $out[] = array_merge([
                    $k === 0 ? $i + 1 : '',
                    $k === 0 ? $row['user']->name : '',
                    $k === 0 ? $p?->jobPosition?->name : '',
                    $k === 0 ? $unit : '',
                ], ($this->detail)($item));
                $excelRow++;
            }

            if ($excelRow > $start) {
                $this->spans[] = [$start, $excelRow - 1];
            }
        }

        return $out;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 6, 'B' => 30, 'C' => 28, 'D' => 28];
        foreach (array_keys($this->detailHeadings) as $n) {
            $widths[Coordinate::stringFromColumnIndex(5 + $n)] = 20;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = Coordinate::stringFromColumnIndex(4 + count($this->detailHeadings));
        $lastRow = max(count($this->array()) + 1, 2);

        foreach ($this->spans as [$a, $b]) {
            if ($b > $a) {
                foreach (['A', 'B', 'C', 'D'] as $col) {
                    $sheet->mergeCells("{$col}{$a}:{$col}{$b}");
                }
            }
        }

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '042c6c']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }
}
