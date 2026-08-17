<?php

namespace App\Exports\Payroll;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel untuk import nominal Jasa Unit per karyawan (App\Imports\Payroll\JasaUnitDetailImport)
 * — kolom `kode` merujuk ke JasaUnitType.code (lihat halaman "Jasa Unit" katalog).
 */
class JasaUnitDetailTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['EMP-001', 'JU-001', 150000],
            ['EMP-002', 'JU-002', 200000],
        ];
    }

    public function headings(): array
    {
        return ['nip', 'kode', 'nominal'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
