<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Lamaran di DB `rekrutmen_db` — record inti seluruh alur rekrutmen. Port dari project
 * `rekrutmen` (App\Models\JobApplication) termasuk state machine `booted()`.
 *
 * BEDA dari aslinya: `logStatusChange()` menulis `changed_by = null` + `changed_by_name`
 * (nama aktor HRIS, snapshot) — id user HRIS ≠ id user `rekrutmen_db`. App `rekrutmen`
 * sendiri tetap menulis `changed_by` FK saat aksi jalan di sana. Lihat migration
 * add_changed_by_name_to_job_application_status_logs di repo `rekrutmen` (2026-09-04).
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

    public const ACTIVE_STATUSES = [self::STATUS_APPLIED, self::STATUS_SCREENING, self::STATUS_INTERVIEW];

    protected static function booted(): void
    {
        static::created(fn (self $app) => $app->logStatusChange(null, $app->status));

        static::updated(function (self $app) {
            if (! $app->wasChanged('status')) {
                return;
            }

            $app->logStatusChange($app->getOriginal('status'), $app->status);

            if (in_array($app->status, [self::STATUS_ONBOARDING, self::STATUS_PASSED], true)) {
                $candidate = Candidate::firstOrCreate(
                    ['user_id' => $app->user_id],
                    ['job_application_id' => $app->id, 'name' => $app->answersMap()['full_name'] ?? $app->user?->name],
                );
                if ($candidate->job_application_id !== $app->id) {
                    $candidate->update(['job_application_id' => $app->id]);
                }
            }

            if ($app->status === self::STATUS_PASSED) {
                $app->closeJobOpeningIfQuotaFilled();
            }
        });
    }

    private function closeJobOpeningIfQuotaFilled(): void
    {
        $opening = $this->jobOpening;
        if (! $opening || ! $opening->is_active || $opening->quota <= 0) {
            return;
        }

        $passedCount = self::where('job_opening_id', $opening->id)->where('status', self::STATUS_PASSED)->count();
        if ($passedCount >= $opening->quota) {
            $opening->update(['is_active' => false]);
        }
    }

    private function logStatusChange(?string $from, string $to): void
    {
        JobApplicationStatusLog::create([
            'job_application_id' => $this->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => null,
            'changed_by_name' => Auth::user()?->name ?? 'Sistem',
            'changed_at' => now(),
        ]);
    }

    /** Catat milestone yang TIDAK mengubah kolom status (mis. hasil tes lulus) agar tetap di Riwayat. */
    public function logMilestone(string $milestone): void
    {
        $this->logStatusChange($this->status, $milestone);
    }

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

    public function answers(): HasMany
    {
        return $this->hasMany(JobApplicationAnswer::class);
    }

    public function candidate(): HasOne
    {
        return $this->hasOne(Candidate::class);
    }

    /** @return array<string,mixed> field_key => value */
    public function answersMap(): array
    {
        return $this->answers->pluck('value', 'field_key')->all();
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }
}
