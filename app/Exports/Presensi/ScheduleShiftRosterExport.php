<?php

namespace App\Exports\Presensi;

use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleShiftTeam;
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
 * Export tab "Detail Penjadwalan" (2026-09-21, layout baru): DUA tabel berdampingan dalam 1 sheet.
 *
 * - KIRI (kolom A-D) tabel legenda: judul "JADWAL {nama jadwal}", "Periode Penjadwalan {bulan}", lalu
 *   Kode Shift | Keterangan (nama lengkap shift) | Jam Kerja | Tim (tim yang boleh memakai shift itu).
 * - KANAN (mulai kolom F, E = jarak kosong) grid roster: 1 baris = 1 karyawan, kolom tanggal 1..akhir
 *   bulan, sel diisi kode shift/"L" dan diwarnai sesuai warna shift (sama seperti tabel di web).
 *
 * Pengelompokan Tim ikut $teamGroupMode grid web - 'row' (default): BARIS pemisah banner (merge
 * lebar grid) tiap kali grup tim berganti; 'column': kolom "Tim" di depan kolom Karyawan, di-merge
 * vertikal per grup (padanan rowspan di web).
 *
 * Import (ScheduleShiftRosterTab::updatedImportFile) mencari kolom "Karyawan" di baris header (baris 3),
 * jadi posisi grid tidak di-hardcode - file lama (grid mulai kolom A) tetap terbaca.
 */
class ScheduleShiftRosterExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    /** Jumlah kolom tabel legenda (A-D); kolom E jarak kosong; grid mulai kolom F. */
    private const LEGEND_COLS = 4;

    private const GRID_START_INDEX = self::LEGEND_COLS + 1; // 0-based index kolom pertama grid (F)

    private const HEADER_ROW = 3;

    /** @var array<int, string> baris SHEET (1-based) => label banner tim - dipakai mode 'row'. */
    private array $separatorRows = [];

    /** @var array<int, array{0: int, 1: int, 2: string}> [baris sheet awal, baris sheet akhir, label]
     * per grup tim - dipakai mode 'column' buat merge vertikal kolom Tim. */
    private array $teamMergeRows = [];

    /** @var array<int, array{0: int, 1: \App\Models\User}> [baris sheet, karyawan] - buat mewarnai sel. */
    private array $employeeRows = [];

    /** @var array<int, array{0: int, 1: string, 2: string}> [baris sheet awal, akhir, label] grup
     * kolom Tim di legenda yang di-merge vertikal (nilai sama berurutan). */
    private array $legendTeamMerges = [];

    private int $legendLastRow = self::HEADER_ROW;

    private int $gridLastRow = self::HEADER_ROW;

    /** @var Collection<int, ScheduleShiftTeam>|null */
    private ?Collection $teams = null;

    /**
     * @param  Collection<int, \App\Models\User>  $employees  Sudah diurut per tim kalau
     *   $schedule->use_teams (lihat ScheduleShiftRosterTab::getEmployeesProperty()) - urutan ini
     *   yang dipakai buat nentuin kapan grup tim baru dimulai, BUKAN dihitung ulang di sini.
     * @param  array<int, \Carbon\Carbon>  $dates
     * @param  array<string, array<string, string>>  $roster  [user_id => [date => shift_type_id|'off']]
     * @param  array<string, ?string>  $teamNames  [user_id => nama tim, null/tidak ada = Tanpa
     *   Tim] - cuma dipakai kalau $schedule->use_teams.
     * @param  string  $teamGroupMode  'row' atau 'column' - sama nilai ScheduleShiftRosterTab::$teamGroupMode.
     * @param  array<string, string>  $leaveCells  "{user_id}|{Y-m-d}" => 'cuti'|'izin' - sel off_day
     *   yang asalnya dari Cuti/Izin disetujui otomatis (ScheduleShiftRosterTab::$leaveCells,
     *   instruksi user 2026-09-24), diberi kode "C"/"I" tersendiri (beda dari "L" Libur biasa) +
     *   warna beda, supaya kelihatan jelas di export & tidak disangka Libur manual biasa.
     */
    public function __construct(
        protected Schedule $schedule,
        protected Collection $employees,
        protected array $dates,
        protected array $roster,
        protected string $periodLabel,
        protected array $teamNames = [],
        protected string $teamGroupMode = 'row',
        protected array $leaveCells = [],
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

    private function isColumnMode(): bool
    {
        return $this->useTeams() && $this->teamGroupMode === 'column';
    }

    /** Lebar grid: [Tim,] Karyawan, tanggal... */
    private function gridWidth(): int
    {
        return ($this->isColumnMode() ? 2 : 1) + count($this->dates);
    }

    private function gridStartCol(): string
    {
        return Coordinate::stringFromColumnIndex(self::GRID_START_INDEX + 1);
    }

    private function nameCol(): string
    {
        return Coordinate::stringFromColumnIndex(self::GRID_START_INDEX + 1 + ($this->isColumnMode() ? 1 : 0));
    }

    private function lastCol(): string
    {
        return Coordinate::stringFromColumnIndex(self::GRID_START_INDEX + $this->gridWidth());
    }

    /** Hex CSS (#rgb / #rrggbb) -> 'RRGGBB' buat PhpSpreadsheet, null kalau bukan hex. */
    private function hex(?string $color): ?string
    {
        $c = ltrim(trim((string) $color), '#');

        if (preg_match('/^[0-9a-fA-F]{3}$/', $c)) {
            $c = $c[0].$c[0].$c[1].$c[1].$c[2].$c[2];
        }

        return preg_match('/^[0-9a-fA-F]{6}$/', $c) ? strtoupper($c) : null;
    }

    private function offBg(): string
    {
        return $this->hex($this->schedule->off_bg_color) ?? 'EC8888';
    }

    private function offFont(): string
    {
        return $this->hex($this->schedule->off_font_color) ?? 'FFFFFF';
    }

    /** Warna kode "C" (Cuti disetujui otomatis) - tetap, tidak ikut off_bg_color/off_font_color
     *  jadwal (beda konsep dari Libur manual biasa). */
    private function cutiBg(): string
    {
        return 'EF4444';
    }

    private function cutiFont(): string
    {
        return '000000';
    }

    /** Warna kode "Izin" (Izin disetujui otomatis). */
    private function izinBg(): string
    {
        return '0EA5E9';
    }

    private function izinFont(): string
    {
        return '000000';
    }

    /** 'cuti'|'izin'|null buat sel off_day karyawan+tanggal ini. */
    private function leaveTypeFor(\App\Models\User $employee, \Carbon\Carbon $date): ?string
    {
        return $this->leaveCells[$employee->id.'|'.$date->toDateString()] ?? null;
    }

    private function teams(): Collection
    {
        return $this->teams ??= $this->schedule->use_teams
            ? ScheduleShiftTeam::where('schedule_id', $this->schedule->id)->with('shiftTypes')->orderBy('name')->get()
            : collect();
    }

    /** Warna tim (hex 'RRGGBB') menurut nama tim - null kalau tidak ada tim itu / warna bukan hex (mis. "Tanpa Tim"). */
    private function teamHex(string $teamName): ?string
    {
        return $this->hex($this->teams()->firstWhere('name', $teamName)?->color);
    }

    /** Tim yang boleh memakai shift ini (tim tanpa batasan = boleh semua shift). */
    private function allowedTeamsFor($shiftType): Collection
    {
        return $this->teams()->filter(fn ($t) => $t->shiftTypes->isEmpty() || $t->shiftTypes->contains('id', $shiftType->id));
    }

    /** Kolom "Tim" legenda: tim yang boleh memakai shift ini (tim tanpa batasan = boleh semua shift). */
    private function teamsTextFor($shiftType): string
    {
        $teams = $this->teams();

        if ($teams->isEmpty()) {
            return '-';
        }

        $allowed = $this->allowedTeamsFor($shiftType);

        return match (true) {
            $allowed->isEmpty() => '-',
            $allowed->count() === $teams->count() => 'Semua Tim',
            default => $allowed->pluck('name')->implode(', '),
        };
    }

    /** @return array<int, array{0: string, 1: string, 2: string, 3: string}> [kode, keterangan, jam kerja, tim] */
    private function legendRows(): array
    {
        $rows = [
            ['L', 'Libur', '-', '-'],
            ['C', 'Cuti (disetujui otomatis)', '-', '-'],
            ['Izin', 'Izin (disetujui otomatis)', '-', '-'],
        ];

        foreach ($this->schedule->shiftTypes as $type) {
            $start = substr((string) $type->start_time, 0, 5);
            $end = substr((string) $type->end_time, 0, 5);

            $rows[] = [
                (string) $type->code,
                (string) $type->name,
                ($start !== '' && $end !== '') ? "{$start} s.d {$end}" : '-',
                $this->teamsTextFor($type),
            ];
        }

        return $rows;
    }

    public function array(): array
    {
        $this->separatorRows = [];
        $this->teamMergeRows = [];
        $this->employeeRows = [];
        $this->legendTeamMerges = [];

        $columnMode = $this->isColumnMode();
        $useTeams = $this->useTeams();
        $dateCount = count($this->dates);
        $gridWidth = $this->gridWidth();

        // Grid: index 0 = header (baris sheet 3), sisanya data mulai baris sheet 4.
        $header = $columnMode ? ['Tim', 'Karyawan'] : ['Karyawan'];
        foreach ($this->dates as $date) {
            // Tanggal + nama hari (2 baris dalam 1 sel), wrapText diaktifkan di styles().
            $header[] = $date->format('d')."\n".$date->translatedFormat('D');
        }

        $grid = [$header];
        $lastTeam = null;
        $groupStartSheetRow = null;

        foreach ($this->employees as $employee) {
            $teamCell = '';

            if ($useTeams) {
                $team = $this->teamNameFor($employee);

                if ($team !== $lastTeam) {
                    $sheetRow = self::HEADER_ROW + count($grid);

                    if ($columnMode) {
                        // Tutup rentang grup sebelumnya: [baris pertama grup itu, baris SEBELUM baris ini].
                        if ($groupStartSheetRow !== null) {
                            $this->teamMergeRows[] = [$groupStartSheetRow, $sheetRow - 1, $lastTeam];
                        }
                        $groupStartSheetRow = $sheetRow;
                        $teamCell = $team;
                    } else {
                        $this->separatorRows[$sheetRow] = $team;
                        $grid[] = array_merge([$team], array_fill(0, $dateCount, ''));
                    }

                    $lastTeam = $team;
                }
            }

            $userRoster = $this->roster[$employee->id] ?? [];
            $row = $columnMode ? [$teamCell, $employee->name] : [$employee->name];

            foreach ($this->dates as $date) {
                $value = $userRoster[$date->toDateString()] ?? '';
                $row[] = match (true) {
                    $value !== 'off' => $this->schedule->shiftTypes->firstWhere('id', $value)?->code ?? '',
                    $this->leaveTypeFor($employee, $date) === 'cuti' => 'C',
                    $this->leaveTypeFor($employee, $date) === 'izin' => 'Izin',
                    default => 'L',
                };
            }

            $this->employeeRows[] = [self::HEADER_ROW + count($grid), $employee];
            $grid[] = $row;
        }

        if ($columnMode && $groupStartSheetRow !== null) {
            $this->teamMergeRows[] = [$groupStartSheetRow, self::HEADER_ROW + count($grid) - 1, $lastTeam];
        }

        // Legenda: header (baris 3) + baris data mulai baris 4.
        $legendData = $this->legendRows();
        $legend = array_merge([['Kode Shift', 'Keterangan', 'Jam Kerja', 'Tim']], $legendData);

        $this->computeLegendTeamMerges($legendData);

        $bodyRows = max(count($grid), count($legend));
        $this->gridLastRow = self::HEADER_ROW + count($grid) - 1;
        $this->legendLastRow = self::HEADER_ROW + count($legend) - 1;

        $blankLegend = array_fill(0, self::LEGEND_COLS, '');
        $blankGrid = array_fill(0, $gridWidth, '');

        $rows = [
            array_merge(['JADWAL '.mb_strtoupper($this->schedule->name)], array_fill(0, self::GRID_START_INDEX + $gridWidth - 1, '')),
            array_merge(["Periode Penjadwalan {$this->periodLabel}"], array_fill(0, self::GRID_START_INDEX + $gridWidth - 1, '')),
        ];

        for ($i = 0; $i < $bodyRows; $i++) {
            $rows[] = array_merge($legend[$i] ?? $blankLegend, [''], $grid[$i] ?? $blankGrid);
        }

        return $rows;
    }

    /** Nilai kolom Tim legenda yang sama berurutan (bukan '-') di-merge vertikal, seperti kolom Unit di contoh. */
    private function computeLegendTeamMerges(array $legendData): void
    {
        $runStart = null;
        $runValue = null;

        foreach ($legendData as $i => $row) {
            $sheetRow = self::HEADER_ROW + 1 + $i;
            $value = $row[3];

            if ($value === $runValue && $value !== '-') {
                continue;
            }

            if ($runStart !== null && $sheetRow - 1 > $runStart) {
                $this->legendTeamMerges[] = [$runStart, $sheetRow - 1, (string) $runValue];
            }

            $runStart = $sheetRow;
            $runValue = $value;
        }

        $lastSheetRow = self::HEADER_ROW + count($legendData);
        if ($runStart !== null && $lastSheetRow > $runStart && $runValue !== '-') {
            $this->legendTeamMerges[] = [$runStart, $lastSheetRow, (string) $runValue];
        }
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 11, 'B' => 26, 'C' => 20, 'D' => 24, 'E' => 3];

        $col = self::GRID_START_INDEX + 1;

        if ($this->isColumnMode()) {
            $widths[Coordinate::stringFromColumnIndex($col++)] = 14;
        }

        $widths[Coordinate::stringFromColumnIndex($col++)] = 30;

        foreach ($this->dates as $ignored) {
            $widths[Coordinate::stringFromColumnIndex($col++)] = 4.5;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        // array() HARUS sudah dipanggil sebelum styles() (dipanggil framework buat isi sheet, atau
        // manual di test) - properti posisi baris keisi lewat efek samping itu.
        $lastCol = $this->lastCol();
        $gridStart = $this->gridStartCol();
        $nameCol = $this->nameCol();
        $columnMode = $this->isColumnMode();
        $legendFill = 'FFF2CC';
        $headerRow = self::HEADER_ROW;

        // ---- Legenda (kiri) ----
        $sheet->mergeCells('A1:D1');
        $sheet->mergeCells('A2:D2');
        $sheet->getStyle('A1:D2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $legendFill]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(2)->setRowHeight(22);

        $sheet->getStyle("A{$headerRow}:D{$headerRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $legendFill]],
        ]);

        $sheet->getStyle("A{$headerRow}:D{$this->legendLastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle("B".($headerRow + 1).":B{$this->legendLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Kode shift diwarnai sesuai warna shift (Libur: warna Libur jadwal) - 3 baris pertama
        // legenda tetap (L, C, I), shift jadwal menyusul mulai baris ke-4.
        $sheet->getStyle('A'.($headerRow + 1))->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $this->offFont()]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->offBg()]],
        ]);
        $sheet->getStyle('A'.($headerRow + 2))->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $this->cutiFont()]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->cutiBg()]],
        ]);
        $sheet->getStyle('A'.($headerRow + 3))->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $this->izinFont()]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->izinBg()]],
        ]);

        foreach ($this->schedule->shiftTypes->values() as $i => $type) {
            $row = $headerRow + 4 + $i;
            $bg = $this->hex($type->color);

            $style = ['font' => ['bold' => true, 'color' => ['rgb' => $this->hex($type->font_color) ?? '000000']]];
            if ($bg) {
                $style['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]];
            }

            $sheet->getStyle("A{$row}")->applyFromArray($style);

            // Kolom Tim: kalau HANYA satu tim (dari beberapa) yang boleh memakai shift ini, sel-nya
            // diwarnai warna tim itu, font putih. Banyak tim / "Semua Tim" tidak diwarnai (1 sel tidak
            // bisa memuat beberapa warna).
            $teams = $this->teams();
            $allowed = $this->allowedTeamsFor($type);
            $teamBg = ($teams->count() > 1 && $allowed->count() === 1) ? $this->hex($allowed->first()->color) : null;
            if ($teamBg) {
                $sheet->getStyle("D{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $teamBg]],
                ]);
            }
        }

        foreach ($this->legendTeamMerges as [$start, $end, $label]) {
            $sheet->mergeCells("D{$start}:D{$end}");
            $sheet->getStyle("D{$start}")->getFont()->setBold(true);
        }

        // ---- Grid roster (kanan) ----
        $sheet->getStyle("{$gridStart}{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2595EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        // Header tanggal 2 baris ("01\nSen") - tinggikan barisnya biar nama harinya tidak kepotong.
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        $sheet->getStyle("{$gridStart}{$headerRow}:{$lastCol}{$this->gridLastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $firstDataRow = $headerRow + 1;
        $sheet->getStyle("{$nameCol}{$firstDataRow}:{$nameCol}{$this->gridLastRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            'font' => ['bold' => true],
        ]);

        if ($columnMode) {
            $this->styleTeamMergeRows($sheet, $gridStart);
        } else {
            $this->styleSeparatorRows($sheet, $gridStart, $lastCol);
        }

        $this->colorRosterCells($sheet);

        // Freeze baris judul + header saja (kolom tidak dibekukan - tabel legenda di kiri lebar).
        $sheet->freezePane('A'.($headerRow + 1));

        return [];
    }

    /** Warnai sel jadwal sesuai warna shift (font ikut font_color), "L" pakai warna Libur jadwal. */
    private function colorRosterCells(Worksheet $sheet): void
    {
        $dateStartIndex = self::GRID_START_INDEX + 1 + ($this->isColumnMode() ? 1 : 0) + 1;
        $shiftTypes = $this->schedule->shiftTypes;

        foreach ($this->employeeRows as [$sheetRow, $employee]) {
            $userRoster = $this->roster[$employee->id] ?? [];

            foreach ($this->dates as $i => $date) {
                $value = $userRoster[$date->toDateString()] ?? '';

                if ($value === '') {
                    continue;
                }

                if ($value === 'off') {
                    $leaveType = $this->leaveTypeFor($employee, $date);
                    $bg = match ($leaveType) {
                        'cuti' => $this->cutiBg(),
                        'izin' => $this->izinBg(),
                        default => $this->offBg(),
                    };
                    $font = match ($leaveType) {
                        'cuti' => $this->cutiFont(),
                        'izin' => $this->izinFont(),
                        default => $this->offFont(),
                    };
                } else {
                    $type = $shiftTypes->firstWhere('id', $value);
                    $bg = $this->hex($type?->color);
                    $font = $this->hex($type?->font_color) ?? '000000';
                }

                $cell = Coordinate::stringFromColumnIndex($dateStartIndex + $i).$sheetRow;
                $style = ['font' => ['bold' => true, 'color' => ['rgb' => $font]]];

                if ($bg) {
                    $style['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]];
                }

                $sheet->getStyle($cell)->applyFromArray($style);
            }
        }
    }

    /** Banner tim - merge penuh lebar grid per baris pemisah, versi Excel dari baris pemisah (colspan) di web. */
    private function styleSeparatorRows(Worksheet $sheet, string $gridStart, string $lastCol): void
    {
        foreach ($this->separatorRows as $row => $label) {
            // Warna banner = warna tim (font putih), sama seperti baris pemisah di web; "Tanpa Tim" abu-abu.
            $teamBg = $this->teamHex($label);

            $sheet->mergeCells("{$gridStart}{$row}:{$lastCol}{$row}");
            $sheet->setCellValue("{$gridStart}{$row}", $label);
            $sheet->getStyle("{$gridStart}{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => $teamBg ? 'FFFFFF' : '000000']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $teamBg ?? 'E2E8F0']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }
    }

    /** Merge vertikal kolom Tim per grup - versi Excel dari <td rowspan> di web mode Kolom. */
    private function styleTeamMergeRows(Worksheet $sheet, string $timCol): void
    {
        foreach ($this->teamMergeRows as [$startRow, $endRow, $label]) {
            if ($endRow > $startRow) {
                $sheet->mergeCells("{$timCol}{$startRow}:{$timCol}{$endRow}");
            }

            $teamBg = $this->teamHex($label);

            $sheet->setCellValue("{$timCol}{$startRow}", $label);
            $sheet->getStyle("{$timCol}{$startRow}:{$timCol}{$endRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $teamBg ?? 'E2E8F0']],
                'font' => ['bold' => true, 'color' => ['rgb' => $teamBg ? 'FFFFFF' : '000000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }
    }
}
