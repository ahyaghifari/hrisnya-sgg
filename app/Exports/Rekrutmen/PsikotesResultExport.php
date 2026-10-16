<?php

namespace App\Exports\Rekrutmen;

use App\Models\Rekrutmen\Quiz;
use App\Models\Rekrutmen\QuizAttempt;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Hasil kuis jenis `psikotes` (CFIT) 1 lowongan, meniru planning/PERHITUNGAN_CFIT.xlsx:
 * sheet "Hasil" = baris kunci jawaban, jawaban tiap peserta per soal, skor per subtes (rumus
 * SUMPRODUCT kunci vs jawaban), skor total, IQ + klasifikasi (VLOOKUP) ke sheet "Tabel IQ".
 */
class PsikotesResultExport
{
    /** Norma CFIT: skor mentah 49 turun ke 0 => [IQ, klasifikasi]. Skor > 49 dihitung 49. */
    private const NORMA = [
        [183, 'GENIUS'], [179, 'GENIUS'], [176, 'GENIUS'], [173, 'GENIUS'],
        [169, 'VERY SUPERIOR'], [167, 'VERY SUPERIOR'], [165, 'VERY SUPERIOR'], [161, 'VERY SUPERIOR'],
        [157, 'VERY SUPERIOR'], [155, 'VERY SUPERIOR'], [152, 'VERY SUPERIOR'], [149, 'VERY SUPERIOR'],
        [145, 'VERY SUPERIOR'], [142, 'VERY SUPERIOR'], [140, 'VERY SUPERIOR'],
        [137, 'SUPERIOR'], [133, 'SUPERIOR'], [131, 'SUPERIOR'], [128, 'SUPERIOR'], [124, 'SUPERIOR'], [121, 'SUPERIOR'],
        [119, 'HIGH AVERAGE'], [116, 'HIGH AVERAGE'], [113, 'HIGH AVERAGE'],
        [109, 'AVERAGE'], [106, 'AVERAGE'], [103, 'AVERAGE'], [100, 'AVERAGE'], [96, 'AVERAGE'], [94, 'AVERAGE'], [91, 'AVERAGE'],
        [88, 'LOW AVERAGE'], [85, 'LOW AVERAGE'], [81, 'LOW AVERAGE'],
        [78, 'BORDERLINE MENTAL RETARDATION'], [75, 'BORDERLINE MENTAL RETARDATION'],
        [72, 'BORDERLINE MENTAL RETARDATION'], [70, 'BORDERLINE MENTAL RETARDATION'],
        [67, 'MILD MENTAL RETARDATION'], [63, 'MILD MENTAL RETARDATION'], [60, 'MILD MENTAL RETARDATION'],
        [57, 'MILD MENTAL RETARDATION'], [55, 'MILD MENTAL RETARDATION'], [52, 'MILD MENTAL RETARDATION'],
        [48, 'MODERATE MENTAL RETARDATION'], [47, 'MODERATE MENTAL RETARDATION'], [45, 'MODERATE MENTAL RETARDATION'],
        [43, 'MODERATE MENTAL RETARDATION'], [40, 'MODERATE MENTAL RETARDATION'], [38, 'MODERATE MENTAL RETARDATION'],
    ];

    private const KEY_ROW = 1;
    private const HEAD_ROW = 3;
    private const FIRST_ROW = 4;

    public function __construct(private string $jobOpeningId, private string $quizId) {}

    public function download(string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = $this->build();

        return response()->streamDownload(fn () => (new Xlsx($spreadsheet))->save('php://output'), $filename);
    }

    /** Jawaban multi-pilihan disimpan terurut + dipisah koma (project rekrutmen); kunci dinormalkan sama. */
    private static function normalize(?string $v): string
    {
        return collect(explode(',', (string) $v))->map(fn ($s) => trim($s))->filter()->sort()->implode(', ');
    }

    private function build(): Spreadsheet
    {
        $attempts = QuizAttempt::with(['answers', 'jobApplication.jobOpening'])
            ->where('quiz_id', $this->quizId)
            ->where('status', 'submitted')
            ->whereHas('jobApplication', fn ($q) => $q->where('job_opening_id', $this->jobOpeningId))
            ->orderBy('submitted_at')
            ->get()
            ->values();

        // Soal diambil dari kuisnya (bukan dari attempt) supaya kunci tetap tampil walau belum ada peserta.
        $sections = Quiz::with('questions.section')->find($this->quizId)->questions
            ->sortBy(fn ($q) => $q->section?->sort_order ?? -1)->values()
            ->groupBy(fn ($q) => $q->quiz_section_id ?? '')->values();

        $spreadsheet = new Spreadsheet;
        $ws = $spreadsheet->getActiveSheet()->setTitle('Hasil');
        $lastRow = self::FIRST_ROW + max($attempts->count(), 1) - 1;

        $ws->setCellValue('A' . self::KEY_ROW, 'KUNCI JAWABAN');
        foreach (['No', 'Nama Lengkap', 'Posisi yang Dilamar', 'Tanggal Submit'] as $i => $h) {
            $ws->setCellValue([$i + 1, self::HEAD_ROW], $h);
        }
        foreach ($attempts as $ai => $a) {
            $r = self::FIRST_ROW + $ai;
            $ws->setCellValue("A{$r}", $ai + 1);
            $ws->setCellValue("B{$r}", $a->jobApplication->answersMap()['full_name'] ?? '');
            $ws->setCellValue("C{$r}", $a->jobApplication->jobOpening?->title);
            $ws->setCellValue("D{$r}", $a->submitted_at?->format('d/m/Y H:i'));
        }

        $col = 5;
        $scoreCols = [];
        $answers = $attempts->map(fn ($a) => $a->answers->keyBy('quiz_question_id'));

        foreach ($sections as $si => $questions) {
            $first = Coordinate::stringFromColumnIndex($col);
            $name = $questions->first()->section?->name ?: 'Subtes ' . ($si + 1);

            foreach ($questions as $qi => $q) {
                $ws->setCellValue([$col, self::HEAD_ROW], $name . ' - ' . ($qi + 1));
                $ws->setCellValue([$col, self::KEY_ROW], self::normalize($q->correct_option));
                foreach ($answers as $ai => $byQuestion) {
                    $ws->setCellValue([$col, self::FIRST_ROW + $ai], self::normalize($byQuestion->get($q->id)?->selected_option));
                }
                $col++;
            }

            $last = Coordinate::stringFromColumnIndex($col - 1);
            $score = Coordinate::stringFromColumnIndex($col);
            $ws->setCellValue($score . self::HEAD_ROW, 'Skor ' . $name);
            for ($r = self::FIRST_ROW; $r <= $lastRow; $r++) {
                $ws->setCellValue("{$score}{$r}", "=SUMPRODUCT(--(\${$first}\$" . self::KEY_ROW . ":\${$last}\$" . self::KEY_ROW . "={$first}{$r}:{$last}{$r}))");
            }
            $scoreCols[] = $score;
            $col++;
        }

        [$total, $iq, $class] = array_map(fn ($i) => Coordinate::stringFromColumnIndex($col + $i), [0, 1, 2]);
        $ws->setCellValue($total . self::HEAD_ROW, 'Skor Total');
        $ws->setCellValue($iq . self::HEAD_ROW, 'IQ');
        $ws->setCellValue($class . self::HEAD_ROW, 'Klasifikasi');
        for ($r = self::FIRST_ROW; $r <= $lastRow; $r++) {
            $ws->setCellValue("{$total}{$r}", '=' . implode('+', array_map(fn ($c) => "{$c}{$r}", $scoreCols)));
            $ws->setCellValue("{$iq}{$r}", "=VLOOKUP(MIN({$total}{$r},49),'Tabel IQ'!\$A\$3:\$C\$52,2,FALSE)");
            $ws->setCellValue("{$class}{$r}", "=VLOOKUP(MIN({$total}{$r},49),'Tabel IQ'!\$A\$3:\$C\$52,3,FALSE)");
        }

        $ws->getStyle("A1:{$class}" . self::HEAD_ROW)->getFont()->setBold(true);
        $ws->getStyle("A1:{$class}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
        $ws->getStyle("A3:{$class}3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
        $ws->freezePane('E' . self::FIRST_ROW);
        foreach (range(1, $col + 2) as $c) {
            $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        $this->normaSheet($spreadsheet);

        return $spreadsheet;
    }

    private function normaSheet(Spreadsheet $spreadsheet): void
    {
        $ws = $spreadsheet->createSheet()->setTitle('Tabel IQ');
        $ws->setCellValue('A1', 'TABEL NORMA CFIT (skor mentah ke IQ). Skor di atas 49 dihitung 49.');
        foreach (['NILAI', 'IQ', 'KLASIFIKASI'] as $i => $h) {
            $ws->setCellValue([$i + 1, 2], $h);
        }
        foreach (self::NORMA as $i => [$iq, $class]) {
            $r = 3 + $i;
            $ws->setCellValue("A{$r}", 49 - $i);
            $ws->setCellValue("B{$r}", $iq);
            $ws->setCellValue("C{$r}", $class);
        }
        $ws->getStyle('A1:C2')->getFont()->setBold(true);
        foreach (['A', 'B', 'C'] as $c) {
            $ws->getColumnDimension($c)->setAutoSize(true);
        }
    }
}
