<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Percobaan pengerjaan kuis oleh kandidat. Pengerjaan sendiri (start/submit) TETAP di
 * project `rekrutmen`. Dari HRIS: read-only (lihat skor, jawaban, durasi) di halaman Tes.
 */
class QuizAttempt extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['job_application_id', 'quiz_id', 'started_at', 'deadline_at', 'submitted_at', 'score', 'status'];

    protected $casts = [
        'started_at' => 'datetime',
        'deadline_at' => 'datetime',
        'submitted_at' => 'datetime',
        'score' => 'decimal:2',
    ];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    /**
     * SEMUA soal kuis dikelompokkan per subtest (urut subtest, lalu urut soal), lengkap dengan
     * jawaban kandidat (null = tidak dijawab). Baris `answers` hanya ada untuk soal yang dijawab,
     * jadi `answers->count()` BUKAN jumlah soal - pakai ini untuk total/ringkasan.
     *
     * @return \Illuminate\Support\Collection<int, array{name:?string,total:int,answered:int,correct:int,items:\Illuminate\Support\Collection}>
     */
    public function questionsBySection(): \Illuminate\Support\Collection
    {
        $this->loadMissing(['quiz.questions.section', 'answers']);
        $answers = $this->answers->keyBy('quiz_question_id');

        return $this->quiz->questions
            ->sortBy(fn ($q) => $q->section?->sort_order ?? -1)->values()
            ->groupBy(fn ($q) => $q->quiz_section_id ?? '')
            ->map(function ($questions) use ($answers) {
                $items = $questions->map(fn ($q) => ['question' => $q, 'answer' => $answers->get($q->id)]);

                return [
                    'name' => $questions->first()->section?->name,
                    'total' => $items->count(),
                    'answered' => $items->filter(fn ($i) => filled($i['answer']?->selected_option))->count(),
                    'correct' => $items->filter(fn ($i) => $i['answer']?->is_correct)->count(),
                    'items' => $items,
                ];
            })->values();
    }

    public function durationLabel(): ?string
    {
        if (! $this->started_at || ! $this->submitted_at) {
            return null;
        }

        $seconds = $this->started_at->diffInSeconds($this->submitted_at);
        $minutes = intdiv($seconds, 60);

        return $minutes > 0 ? "{$minutes} menit " . ($seconds % 60) . ' detik' : ($seconds % 60) . ' detik';
    }
}
