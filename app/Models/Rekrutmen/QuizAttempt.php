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
