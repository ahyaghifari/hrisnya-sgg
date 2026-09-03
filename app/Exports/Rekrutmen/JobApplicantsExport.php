<?php

namespace App\Exports\Rekrutmen;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export daftar pelamar satu lowongan (dari API Rekrutmen). Kolom mengikuti data yang
 * dikirim endpoint `.../job-openings/{id}/applications`. Kolom tambahan (No. HP, tgl
 * melamar, pendidikan, dst) baru bisa muncul kalau endpoint itu ikut mengirimnya.
 *
 * @param  list<array{job_application_id?:string,applicant_name?:?string,applicant_email?:?string,status?:string,updated_at?:?string}>  $rows
 * @param  array<string,string>  $statusLabels  status mentah => label tampil
 */
class JobApplicantsExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        protected array $rows,
        protected array $statusLabels = [],
        protected ?string $jobOpeningTitle = null,
    ) {}

    public function title(): string
    {
        return 'Pelamar';
    }

    public function headings(): array
    {
        return ['No', 'Nama Pelamar', 'Email', 'Status', 'Update Terakhir'];
    }

    public function array(): array
    {
        return array_values(array_map(function ($row, $i) {
            $status = $row['status'] ?? '';

            return [
                $i + 1,
                $row['applicant_name'] ?? '',
                $row['applicant_email'] ?? '',
                $this->statusLabels[$status] ?? $status,
                ! empty($row['updated_at']) ? Carbon::parse($row['updated_at'])->translatedFormat('d M Y H:i') : '',
            ];
        }, $this->rows, array_keys($this->rows)));
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 32, 'C' => 34, 'D' => 20, 'E' => 20];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
