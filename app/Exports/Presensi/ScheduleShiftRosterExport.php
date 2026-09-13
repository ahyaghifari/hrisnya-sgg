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
 *
 * Pengelompokan Tim (2026-09-15, instruksi user) - BARIS pemisah bergaya banner (merge penuh
 * lebar tabel) tiap kali grup tim berganti, PERSIS pola tabel Detail Penjadwalan di web (bukan
 * kolom "Tim" + merge vertikal seperti versi sebelumnya) - lebih gampang dibaca & tidak perlu
 * kolom ekstra yang mepetkan lebar kolom nama.
 */
class ScheduleShiftRosterExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    /** @var array<int, string> raw row index (dari array(), 0-based) => label banner tim -
     * diisi array(), dibaca styles() buat merge+warnai barisnya. Row index header selalu 0. */
    private array $separatorRows = [];

    /** Total baris hasil array() (header + banner tim + karyawan) - dipakai styles() hitung
     * dataLastRow tanpa perlu array() dipanggil ulang. */
    private int $totalRows = 0;

    /**
     * @param  Collection<int, \App\Models\User>  $employees  Sudah diurut per tim kalau
     *   $schedule->use_teams (lihat ScheduleShiftRosterTab::getEmployeesProperty()) - urutan ini
     *   yang dipakai buat nentuin kapan banner tim baru disisipkan, BUKAN dihitung ulang di sini.
     * @param  array<int, \Carbon\Carbon>  $dates
     * @param  array<string, array<string, string>>  $roster  [user_id => [date => shift_type_id|'off']]
     * @param  array<string, ?string>  $teamNames  [user_id => nama tim, null/tidak ada = Tanpa
     *   Tim] - cuma dipakai kalau $schedule->use_teams.
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

    private function useTeams(): bool
    {
        return $this->schedule->use_teams && $this->teamNames !== [];
    }

    private function teamNameFor(\App\Models\User $employee): string
    {
        return $this->teamNames[$employee->id] ?? 'Tanpa Tim';
    }

    public function array(): array
    {
        $rows = [];
        $this->separatorRows = [];
        $dateCount = count($this->dates);

        $header = ['Karyawan'];
        foreach ($this->dates as $date) {
            // Tanggal + nama hari (2 baris dalam 1 sel) - sama info kayak header tabel Detail
            // Penjadwalan (tanggal di atas, hari di bawah). wrapText diaktifkan di styles().
            $header[] = $date->format('d')."\n".$date->translatedFormat('D');
        }
        $rows[] = $header;

        $lastTeam = null;
        $useTeams = $this->useTeams();

        foreach ($this->employees as $employee) {
            if ($useTeams) {
                $team = $this->teamNameFor($employee);

                if ($team !== $lastTeam) {
                    $this->separatorRows[count($rows)] = $team;
                    $rows[] = array_merge([$team], array_fill(0, $dateCount, ''));
                    $lastTeam = $team;
                }
            }

            $userRoster = $this->roster[$employee->id] ?? [];
            $row = [$employee->name];

            foreach ($this->dates as $date) {
                $value = $userRoster[$date->toDateString()] ?? '';
                $row[] = $value === 'off' ? 'L' : ($this->schedule->shiftTypes->firstWhere('id', $value)?->code ?? '');
            }

            $rows[] = $row;
        }

        $this->totalRows = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 22];

        foreach ($this->dates as $i => $date) {
            $widths[Coordinate::stringFromColumnIndex($i + 2)] = 4.5;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = Coordinate::stringFromColumnIndex(1 + count($this->dates));
        // array() HARUS sudah dipanggil sebelum styles() (dipanggil framework buat isi sheet,
        // atau manual di test) - $this->totalRows/$separatorRows sudah keisi lewat efek samping
        // itu, lihat docblock properti di atas.
        $dataLastRow = $this->totalRows + 2;

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
        $sheet->getStyle("A4:A{$dataLastRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            'font' => ['bold' => true],
        ]);

        $this->styleSeparatorRows($sheet, $lastCol);

        // Freeze panes — baris 1-3 (judul, periode, header) + kolom Karyawan tetap kelihatan pas
        // scroll tanggal, sama konsep sticky di tabel Detail Penjadwalan.
        $sheet->freezePane("B4");

        // ponytail: sel jadwal sengaja tidak diwarnai (instruksi user). Kode shift + "L" +
        // legenda sudah cukup; kalau nanti perlu versi berwarna lagi, kembalikan loop
        // pewarnaan per-sel pakai ScheduleShiftType->color / font_color.

        return [];
    }

    /** Banner tim - merge penuh lebar tabel per baris pemisah, versi Excel dari baris pemisah
     * (colspan) di tabel Detail Penjadwalan web. Row index di $separatorRows itu RAW (dari
     * array(), 0-based) - sheet row-nya = rawIndex + 3 (geser 2 baris judul yang disisipkan
     * insertNewRowBefore(1,2), + 1 karena array 0-based vs baris Excel 1-based). */
    private function styleSeparatorRows(Worksheet $sheet, string $lastCol): void
    {
        foreach ($this->separatorRows as $rawIndex => $label) {
            $row = $rawIndex + 3;

            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }
    }

    private function legendText(): string
    {
        $parts = $this->schedule->shiftTypes->map(fn ($t) => "{$t->code}={$t->name}")->all();
        $parts[] = 'L=Libur';

        return implode(', ', $parts);
    }
}
