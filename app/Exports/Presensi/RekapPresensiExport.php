<?php

namespace App\Exports\Presensi;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export generik Rekap Presensi (Harian / Periode / Kehadiran): heading + baris sudah jadi dari RekapPresensiPage::exportExcel(). */
class RekapPresensiExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    public function __construct(private array $headings, private array $rows) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
