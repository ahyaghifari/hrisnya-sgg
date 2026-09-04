<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lamaran di DB `rekrutmen_db` — record inti seluruh alur rekrutmen.
 *
 * FASE 1a: read-only dari HRIS. State machine `booted()` (auto status-log, auto-buat
 * Candidate saat onboarding/passed, auto-close lowongan saat kuota penuh) DIPORT di fase
 * jalur-tulis berikutnya, bersama migration `*_by_name` di repo `rekrutmen` untuk aktor HRIS.
 */
class JobApplication extends Model
{
    use HasUlids, SoftDeletes;

    protected $connection = 'recruitment';

    protected $fillable = [
        'user_id', 'job_opening_id', 'status', 'quiz_sent_at', 'quiz_result',
        'psychotest_completed_at', 'interview_result', 'offering_letter_path',
        'offering_letter_uploaded_at', 'offering_letter_signed_path', 'offering_letter_signed_at',
    ];

    protected $casts = [
        'quiz_sent_at' => 'datetime',
        'psychotest_completed_at' => 'datetime',
        'offering_letter_uploaded_at' => 'datetime',
        'offering_letter_signed_at' => 'datetime',
    ];

    public const STATUS_APPLIED = 'applied';
    public const STATUS_SCREENING = 'screening';
    public const STATUS_SHORTLISTED = 'shortlisted';
    public const STATUS_INTERVIEW = 'interview';
    public const STATUS_OFFERING = 'offering';
    public const STATUS_MCU = 'mcu';
    public const STATUS_ONBOARDING = 'onboarding';
    public const STATUS_PASSED = 'passed';
    public const STATUS_REJECTED = 'rejected';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(JobApplicationStatusLog::class)->orderBy('changed_at');
    }
}
