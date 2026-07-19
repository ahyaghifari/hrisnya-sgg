<?php

namespace App\Exports\Payroll;

use App\Models\Payroll\UserKoperasi;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KoperasiExport implements FromCollection, WithHeadings, WithStyles
{
    /** @param Collection<int, UserKoperasi> $members */
    public function __construct(protected Collection $members) {}

    public function collection(): Collection
    {
        return $this->members->map(fn (UserKoperasi $member) => [
            $member->user?->nip ?? '-',
            $member->user?->name ?? '-',
            (float) $member->amount,
            $member->is_active ? 'Aktif' : 'Nonaktif',
        ]);
    }

    public function headings(): array
    {
        return ['NIP', 'Nama', 'Potongan Per Bulan', 'Status'];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
