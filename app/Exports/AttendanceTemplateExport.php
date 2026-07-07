<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['EMP-001', '2024-01-15', '08:00:00', '17:00:00', 'present'],
            ['EMP-002', '2024-01-15', '08:15:00', '17:15:00', 'present'],
            ['EMP-003', '2024-01-15', '', '', 'absent'],
        ];
    }

    public function headings(): array
    {
        return [
            'nip',
            'date',
            'check_in_time',
            'check_out_time',
            'status'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text.
            1 => ['font' => ['bold' => true]],
        ];
    }
}