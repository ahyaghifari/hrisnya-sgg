<?php

namespace App\Exports\Payroll;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel untuk import Bon/Insentif (kolom sama persis untuk keduanya —
 * lihat App\Imports\Payroll\BonImport / IncentiveImport).
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
