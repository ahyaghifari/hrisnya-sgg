<?php

namespace App\Exports\Payroll;

use App\Models\Payroll\UserKoperasi;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KoperasiExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithStyles, WithCustomValueBinder
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

    // Kolom NIP (A) dipaksa string supaya angka 0 di depan tidak hilang & tidak jadi notasi ilmiah.
    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'A' && $cell->getRow() > 1) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style kolom A utuh (bukan cuma baris terisi) — sel kosong yang diketik manual juga jadi Text.
            'A' => ['numberFormat' => ['formatCode' => NumberFormat::FORMAT_TEXT]],
            1 => ['font' => ['bold' => true]],
        ];
    }
}
