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
 * Rekap hasil kuis jenis `bidang` di 1 lowongan: skor benar per subtes, skor total, dan nilai
 * keseluruhan (%) = benar / jumlah soal * 100. Kuis psikotes: lihat PsikotesResultExport.
 */
class TesBidangResultExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    /** @var Collection<int, QuizAttempt>|null */
    private ?Collection $attempts = null;

    public function __construct(private string $jobOpeningId, private string $quizId) {}

    private function attempts(): Collection
    {
        return $this->attempts ??= QuizAttempt::with(['quiz.questions.section', 'answers', 'jobApplication'])
            ->where('quiz_id', $this->quizId)
            ->where('status', 'submitted')
            ->whereHas('jobApplication', fn ($q) => $q->where('job_opening_id', $this->jobOpeningId))
            ->orderBy('submitted_at')
            ->get();
    }

    public function headings(): array
    {
        $names = $this->attempts()->first()?->questionsBySection()
            ->map(fn ($s, $i) => 'Skor ' . ($s['name'] ?: 'Subtes ' . ($i + 1)))->all() ?? [];

        return array_merge(['No', 'Nama Lengkap', 'Tanggal Submit'], $names, ['Skor Total', 'Nilai Keseluruhan (%)']);
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
                [$correct, $total ? round($correct / $total * 100) : 0],
            );
        });
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
