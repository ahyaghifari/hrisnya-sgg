<?php

namespace App\Exports\Rekrutmen;

use App\Models\Rekrutmen\QuizAttempt;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Rekap hasil 1 kuis di 1 lowongan: skor benar per subtes, total, nilai keseluruhan (%). Kuis jenis
 * `psikotes` ditambah IQ dan klasifikasi (norma CFIT); kuis `bidang` hanya skor. Padanan PERHITUNGAN_CFIT.xlsx (planning/): skor = jumlah jawaban benar
 * (`is_correct` dari project `rekrutmen`), IQ = tabel norma skor mentah -> IQ.
 */
class CfitResultExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    /** skor mentah => [IQ, klasifikasi] (sheet "Tabel IQ"). Tabel hanya sampai 49; skor di atasnya dipakai nilai 49. */
    private const IQ_TABLE = [
        49 => [183, 'GENIUS'], 48 => [179, 'GENIUS'], 47 => [176, 'GENIUS'], 46 => [173, 'GENIUS'],
        45 => [169, 'VERY SUPERIOR'], 44 => [167, 'VERY SUPERIOR'], 43 => [165, 'VERY SUPERIOR'],
        42 => [161, 'VERY SUPERIOR'], 41 => [157, 'VERY SUPERIOR'], 40 => [155, 'VERY SUPERIOR'],
        39 => [152, 'VERY SUPERIOR'], 38 => [149, 'VERY SUPERIOR'], 37 => [145, 'VERY SUPERIOR'],
        36 => [142, 'VERY SUPERIOR'], 35 => [140, 'VERY SUPERIOR'],
        34 => [137, 'SUPERIOR'], 33 => [133, 'SUPERIOR'], 32 => [131, 'SUPERIOR'], 31 => [128, 'SUPERIOR'],
        30 => [124, 'SUPERIOR'], 29 => [121, 'SUPERIOR'],
        28 => [119, 'HIGH AVERAGE'], 27 => [116, 'HIGH AVERAGE'], 26 => [113, 'HIGH AVERAGE'],
        25 => [109, 'AVERAGE'], 24 => [106, 'AVERAGE'], 23 => [103, 'AVERAGE'], 22 => [100, 'AVERAGE'],
        21 => [96, 'AVERAGE'], 20 => [94, 'AVERAGE'], 19 => [91, 'AVERAGE'],
        18 => [88, 'LOW AVERAGE'], 17 => [85, 'LOW AVERAGE'], 16 => [81, 'LOW AVERAGE'],
        15 => [78, 'BORDERLINE MENTAL RETARDATION'], 14 => [75, 'BORDERLINE MENTAL RETARDATION'],
        13 => [72, 'BORDERLINE MENTAL RETARDATION'], 12 => [70, 'BORDERLINE MENTAL RETARDATION'],
        11 => [67, 'MILD MENTAL RETARDATION'], 10 => [63, 'MILD MENTAL RETARDATION'],
        9 => [60, 'MILD MENTAL RETARDATION'], 8 => [57, 'MILD MENTAL RETARDATION'],
        7 => [55, 'MILD MENTAL RETARDATION'], 6 => [52, 'MILD MENTAL RETARDATION'],
        5 => [48, 'MODERATE MENTAL RETARDATION'], 4 => [47, 'MODERATE MENTAL RETARDATION'],
        3 => [45, 'MODERATE MENTAL RETARDATION'], 2 => [43, 'MODERATE MENTAL RETARDATION'],
        1 => [40, 'MODERATE MENTAL RETARDATION'], 0 => [38, 'MODERATE MENTAL RETARDATION'],
    ];

    /** @var Collection<int, QuizAttempt>|null */
    private ?Collection $attempts = null;

    public function __construct(private string $jobOpeningId, private string $quizId, private bool $psikotes = false) {}

    private function attempts(): Collection
    {
        return $this->attempts ??= QuizAttempt::with(['quiz.questions.section', 'answers', 'jobApplication'])
            ->where('quiz_id', $this->quizId)
            ->where('status', 'submitted')
            ->whereHas('jobApplication', fn ($q) => $q->where('job_opening_id', $this->jobOpeningId))
            ->orderBy('submitted_at')
            ->get();
    }

    /** Nama subtes mengikuti urutan soal kuis (sama untuk semua attempt). */
    private function sectionNames(): array
    {
        $first = $this->attempts()->first();

        return $first ? $first->questionsBySection()->pluck('name')->map(fn ($n, $i) => $n ?: 'Subtes ' . ($i + 1))->all() : [];
    }

    public function headings(): array
    {
        return array_merge(
            ['No', 'Nama Lengkap', 'Tanggal Submit'],
            array_map(fn ($n) => "Skor {$n}", $this->sectionNames()),
            $this->psikotes ? ['Skor Total', 'IQ', 'Klasifikasi'] : ['Skor Total', 'Nilai Keseluruhan (%)'],
        );
    }

    public function collection(): Collection
    {
        return $this->attempts()->map(function (QuizAttempt $a, $i) {
            $sections = $a->questionsBySection();
            $correct = $sections->sum('correct');
            $total = $sections->sum('total');

            return array_merge(
                [$i + 1, $a->jobApplication->answersMap()['full_name'] ?? '', $a->submitted_at?->format('d/m/Y H:i')],
                $sections->pluck('correct')->all(),
                $this->psikotes
                    ? [$correct, ...self::IQ_TABLE[min($correct, 49)]]
                    : [$correct, $total ? round($correct / $total * 100) : 0],
            );
        });
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
