<?php

namespace App\Exports\Payroll;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel untuk import Bon (lihat App\Imports\Payroll\BonImport). Import Insentif sudah
 * dihapus - dulu pakai template yang sama (kolom identik), sekarang cuma Bon.
 */
class PayrollManualLineTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['EMP-001', 'Budi Santoso', 500000],
            ['EMP-002', 'Siti Aminah', 250000],
        ];
    }

    public function headings(): array
    {
        return ['nip', 'nama', 'nominal'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
