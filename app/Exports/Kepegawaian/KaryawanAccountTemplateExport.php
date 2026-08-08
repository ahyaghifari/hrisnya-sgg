<?php

namespace App\Exports\Kepegawaian;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Template Excel untuk App\Imports\Kepegawaian\KaryawanAccountImport - cuma 3 kolom, akun
 * dasar (nama/email/nip), bukan profil lengkap seperti EmployeeTemplateExport. */
class KaryawanAccountTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['12345', 'Budi Santoso', 'budi.santoso@example.com'],
            ['12346', 'Siti Aminah', 'siti.aminah@example.com'],
        ];
    }

    public function headings(): array
    {
        return ['nip', 'nama', 'email'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
