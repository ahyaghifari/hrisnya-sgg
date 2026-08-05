<?php

namespace App\Exports\Payroll;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel untuk import Bon mode Detail (lihat App\Imports\Payroll\BonDetailImport) —
 * beda dari PayrollManualLineTemplateExport (mode Simple): kolom `nama_potongan` menggantikan
 * `nama`, wajib diisi (jadi nama baris di slip), dan 1 karyawan boleh punya banyak baris
 * (nama potongan beda-beda).
 */
class BonDetailImportTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['EMP-001', 'Angsuran Motor', 300000],
            ['EMP-001', 'Titipan Koperasi', 200000],
            ['EMP-002', 'Angsuran Motor', 250000],
        ];
    }

    public function headings(): array
    {
        return ['nip', 'nama_potongan', 'nominal'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
