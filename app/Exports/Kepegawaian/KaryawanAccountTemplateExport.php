<?php

namespace App\Exports\Kepegawaian;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Template Excel untuk App\Imports\Kepegawaian\KaryawanAccountImport - akun dasar (nama/email/
 * nip) + profil ringkas (status karyawan/tanggal mulai kerja/tanggal lahir/tempat lahir/alamat),
 * bukan profil selengkap EmployeeTemplateExport. */
class KaryawanAccountTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['12345', 'Budi Santoso', 'budi.santoso@example.com', 'Tetap', '2024-01-15', 'Laki-laki', 'Kawin', 'Banjarbaru', '1995-08-17', 'Islam', 'Jl. Contoh No. 1', '6371012345670001'],
            ['12346', 'Siti Aminah', 'siti.aminah@example.com', 'Kontrak', '2024-03-01', 'Perempuan', 'Belum Kawin', 'Martapura', '1998-11-02', 'Islam', 'Jl. Contoh No. 2', '6371012345670002'],
        ];
    }

    public function headings(): array
    {
        return ['nip', 'nama', 'email', 'status_karyawan', 'tanggal_mulai_kerja', 'jenis_kelamin', 'status_perkawinan', 'tempat_lahir', 'tanggal_lahir', 'agama', 'alamat', 'nik'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
