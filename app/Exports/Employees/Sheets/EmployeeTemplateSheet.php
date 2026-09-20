<?php

namespace App\Exports\Employees\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeTemplateSheet implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    /**
     * Header row + up to 1000 data rows.
     */
    private const DATA_ROWS = 1000;

    public function __construct(protected array $sampleRow = []) {}

    public function title(): string
    {
        return 'Template';
    }

    /**
     * Column map (1-based index → letter):
     *  A-Z  = col 1-26
     *  AA   = col 27  (Agama)
     *  AB   = col 28  (Bank)
     *  AC   = col 29  (Jabatan) - struktur org karyawan diturunkan dari jabatan
     *  AD   = col 30  (Pangkat)
     *  AE   = col 31  (Kelas)
     *  AF   = col 32  (Tipe Karyawan)
     *  AG   = col 33  (Tanggal Bergabung)
     *  AH   = col 34  (Role)
     */
    public function headings(): array
    {
        return [
            'Nama*',                       // A
            'Email*',                      // B
            'Password*',                   // C
            'NIP',                         // D
            'Panggilan',                   // E
            'Jenis Kelamin*',              // F  (dropdown)
            'Tempat Lahir',                // G
            'Tanggal Lahir',               // H  (YYYY-MM-DD)
            'No NIK',                      // I
            'No KK',                       // J
            'No BPJS Kesehatan',           // K
            'No BPJS Ketenagakerjaan',     // L
            'No Rekening',                 // M
            'Nama Rekening',              // N
            'NPWP',                        // O
            'Negara',                      // P
            'Provinsi',                    // Q
            'Kota',                        // R
            'Kecamatan',                   // S
            'Kelurahan',                   // T
            'Alamat',                      // U
            'Kode Pos',                    // V
            'RT',                          // W
            'RW',                          // X
            'No Telepon',                  // Y
            'No WhatsApp',                 // Z
            'Agama',                       // AA (dropdown → sheet Agama)
            'Bank',                        // AB (isian bebas)
            'Jabatan',                     // AC (dropdown → sheet Jabatan)
            'Pangkat',                     // AD (dropdown → sheet Pangkat)
            'Kelas',                       // AE (dropdown → sheet Kelas)
            'Tipe Karyawan',               // AF (dropdown → sheet Tipe_Karyawan)
            'Tanggal Bergabung',           // AG (YYYY-MM-DD)
            'Role',                        // AH (dropdown → sheet Role)
        ];
    }

    public function array(): array
    {
        if (empty($this->sampleRow)) {
            return [];
        }

        return [$this->sampleRow];
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB'],
                ],
            ],
        ];

        // Highlight the sample row in light yellow with italic font
        if (!empty($this->sampleRow)) {
            $styles[2] = [
                'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
                'fill' => [
                    'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FEF9C3'],
                ],
            ];
        }

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet    = $event->sheet->getDelegate();
                $lastRow  = self::DATA_ROWS + 1; // e.g. 1001

                // ── Mark sample row with a comment on cell A2 ────────────────
                if (!empty($this->sampleRow)) {
                    $comment = $sheet->getComment('A2');
                    $comment->getText()->createTextRun('Baris ini adalah contoh data. Hapus baris ini sebelum melakukan import.');
                    $comment->setVisible(false);
                }

                // ── Dropdown: Jenis Kelamin (column F) ───────────────────────
                $this->addListValidation(
                    $sheet,
                    "F2:F{$lastRow}",
                    '"Laki-laki,Perempuan"'
                );

                // ── Dropdowns linked to master-data sheets ───────────────────
                $sheetDropdowns = [
                    'AA' => 'Agama',
                    'AC' => 'Jabatan',
                    'AD' => 'Pangkat',
                    'AE' => 'Kelas',
                    'AF' => 'Tipe_Karyawan',
                    'AH' => 'Role',
                ];

                foreach ($sheetDropdowns as $col => $masterSheet) {
                    $this->addListValidation(
                        $sheet,
                        "{$col}2:{$col}{$lastRow}",
                        "{$masterSheet}!\$A\$2:\$A\$1000"
                    );
                }

                // ── Column widths ────────────────────────────────────────────
                $widths = [
                    'A' => 30, 'B' => 30, 'C' => 20, 'D' => 18, 'E' => 18,
                    'F' => 16, 'G' => 20, 'H' => 16, 'I' => 22, 'J' => 22,
                    'K' => 24, 'L' => 30, 'M' => 22, 'N' => 24, 'O' => 18,
                    'P' => 16, 'Q' => 18, 'R' => 18, 'S' => 18, 'T' => 18,
                    'U' => 30, 'V' => 12, 'W' => 8,  'X' => 8,  'Y' => 18,
                    'Z' => 18, 'AA' => 20, 'AB' => 20, 'AC' => 22, 'AD' => 22,
                    'AE' => 18, 'AF' => 15, 'AG' => 20, 'AH' => 20,
                ];

                foreach ($widths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                // ── Freeze first row ─────────────────────────────────────────
                $sheet->freezePane('A2');
            },
        ];
    }

    private function addListValidation(
        Worksheet $sheet,
        string $range,
        string $formula
    ): void {
        $validation = $sheet->getDataValidation($range);
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_INFORMATION)
            ->setAllowBlank(true)
            ->setShowDropDown(true)   // false = SHOW dropdown in Excel OOXML spec
            ->setShowInputMessage(false)
            ->setShowErrorMessage(false)
            ->setFormula1($formula);
    }
}
