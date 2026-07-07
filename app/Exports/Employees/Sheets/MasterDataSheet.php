<?php

namespace App\Exports\Employees\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MasterDataSheet implements FromArray, WithHeadings, WithTitle, WithStyles
{
    public function __construct(
        protected string $sheetTitle,
        protected string $heading,
        protected array $data,
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function headings(): array
    {
        return [$this->heading];
    }

    public function array(): array
    {
        return array_map(fn ($item) => [$item], $this->data);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
