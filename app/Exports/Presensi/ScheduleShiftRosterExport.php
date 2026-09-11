<?php

namespace App\Exports\Presensi;

use App\Models\Shift\Schedule;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export grid tab "Detail Penjadwalan" — 1 baris = 1 karyawan, kolom tanggal 1..akhir bulan,
 * isi kode shift/"L" (Libur). Sel jadwal sengaja TIDAK diwarnai (instruksi user) — cukup kode
 * teks + legenda; hanya banner judul & baris header yang berwarna.
 */
class ScheduleShiftRosterExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    /**
     * @param  Collection<int, \App\Models\User>  $employees  Sudah diurut per regu kalau
     *   $schedule->use_teams (lihat ScheduleShiftRosterTab::getEmployeesProperty()) - urutan ini
     *   yang dipakai buat nentuin baris mana ikut di-merge di kolom Regu, BUKAN dihitung ulang
     *   di sini.
     * @param  array<int, \Carbon\Carbon>  $dates
     * @param  array<string, array<string, string>>  $roster  [user_id => [date => shift_type_id|'off']]
     * @param  array<string, ?string>  $teamNames  [user_id => nama regu, null/tidak ada = Tanpa
     *   Regu] - cuma dipakai kalau $schedule->use_teams. Kolom "Regu" ditaruh PALING KIRI (sama
     *   posisi kayak tabel Detail Penjadwalan) - updatedImportFile() ikut digeser baca posisinya
     *   (deteksi dari header baris 3, bukan asumsi tetap), lihat method itu.
     */
    public function __construct(
        protected Schedule $schedule,
        protected Collection $employees,
        protected array $dates,
        protected array $roster,
        protected string $periodLabel,
        protected array $teamNames = [],
    ) {}

    public function title(): string
    {
        return 'Detail Penjadwalan';
    }

    private function hasTeamColumn(): bool
    {
        return $this->schedule->use_teams && $this->teamNames !== [];
    }

    private function teamNameFor(\App\Models\User $employee): string
    {
        return $this->teamNames[$employee->id] ?? 'Tanpa Regu';
    }

    public function array(): array
    {
        $rows = [];

        $header = $this->hasTeamColumn() ? ['Regu', 'Karyawan'] : ['Karyawan'];
        foreach ($this->dates as $date) {
            // Tanggal + nama hari (2 baris dalam 1 sel) - sama info kayak header tabel Detail
            // Penjadwalan (tanggal di atas, hari di bawah). wrapText diaktifkan di styles().
            $header[] = $date->format('d')."\n".$date->translatedFormat('D');
        }
        $rows[] = $header;

        foreach ($this->employees as $employee) {
            $userRoster = $this->roster[$employee->id] ?? [];
            $row = $this->hasTeamColumn() ? [$this->teamNameFor($employee), $employee->name] : [$employee->name];

            foreach ($this->dates as $date) {
                $value = $userRoster[$date->toDateString()] ?? '';
                $row[] = $value === 'off' ? 'L' : ($this->schedule->shiftTypes->firstWhere('id', $value)?->code ?? '');
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        $nameCol = Coordinate::stringFromColumnIndex($this->hasTeamColumn() ? 2 : 1);
        $widths = [$nameCol => 22];

        if ($this->hasTeamColumn()) {
            $widths['A'] = 16;
        }

        $dateStartIndex = ($this->hasTeamColumn() ? 2 : 1) + 1;
        foreach ($this->dates as $i => $date) {
            $widths[Coordinate::stringFromColumnIndex($dateStartIndex + $i)] = 4.5;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $nameColIndex = $this->hasTeamColumn() ? 2 : 1;
        $nameCol = Coordinate::stringFromColumnIndex($nameColIndex);
        $lastCol = Coordinate::stringFromColumnIndex($nameColIndex + count($this->dates));
        $dataLastRow = count($this->employees) + 3;

        // Baris judul + keterangan disisipkan di atas grid ("Karyawan" + tanggal) yang datang
        // dari array() — sama pola dengan AttendanceHistoryExport.
        $sheet->insertNewRowBefore(1, 2);
        $sheet->setCellValue('A1', "Penjadwalan — {$this->schedule->name}");
        $sheet->setCellValue('A2', "Periode: {$this->periodLabel} | Keterangan: {$this->legendText()}");
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '042c6c']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'font' => ['italic' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(34);

        $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2595eb']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        // Header tanggal 2 baris ("01\nSen") - tinggikan barisnya biar nama harinya tidak
        // kepotong, sama alasan setRowHeight(24) di baris 2 di atas.
        $sheet->getRowDimension(3)->setRowHeight(26);

        $sheet->getStyle("A3:{$lastCol}{$dataLastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("{$nameCol}4:{$nameCol}{$dataLastRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            'font' => ['bold' => true],
        ]);

        if ($this->hasTeamColumn()) {
            $this->mergeTeamColumn($sheet);
        }

        // Freeze panes — baris 1-3 (judul, periode, header) + kolom Karyawan (& Regu kalau
        // jadwal ini pakai Regu) tetap kelihatan pas scroll tanggal, sama konsep sticky di tabel
        // Detail Penjadwalan. Kolom beku = sampai $nameCol (persis $nameColIndex kolom pertama),
        // jadi otomatis ikut geser kalau Regu ada (nameColIndex 2) atau tidak (nameColIndex 1).
        $freezeCol = Coordinate::stringFromColumnIndex($nameColIndex + 1);
        $sheet->freezePane("{$freezeCol}4");

        // ponytail: sel jadwal sengaja tidak diwarnai (instruksi user). Kode shift + "L" +
        // legenda sudah cukup; kalau nanti perlu versi berwarna lagi, kembalikan loop
        // pewarnaan per-sel pakai ScheduleShiftType->color / font_color.

        return [];
    }

    /** Kolom A (Regu) di-merge per grup baris berurutan yang regu-nya sama — versi Excel dari
     * rowspan di tabel Detail Penjadwalan. $employees HARUS sudah urut per regu (lihat docblock
     * constructor), di sini cuma bandingkan grup berurutan, tidak sort ulang. */
    private function mergeTeamColumn(Worksheet $sheet): void
    {
        $employees = $this->employees->values();
        $count = $employees->count();
        $firstDataRow = 4;

        $i = 0;
        while ($i < $count) {
            $team = $this->teamNameFor($employees[$i]);
            $j = $i;

            while ($j + 1 < $count && $this->teamNameFor($employees[$j + 1]) === $team) {
                $j++;
            }

            $startRow = $firstDataRow + $i;
            $endRow = $firstDataRow + $j;

            if ($endRow > $startRow) {
                $sheet->mergeCells("A{$startRow}:A{$endRow}");
            }

            $sheet->getStyle("A{$startRow}")->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);

            $i = $j + 1;
        }
    }

    private function legendText(): string
    {
        $parts = $this->schedule->shiftTypes->map(fn ($t) => "{$t->code}={$t->name}")->all();
        $parts[] = 'L=Libur';

        return implode(', ', $parts);
    }
}
