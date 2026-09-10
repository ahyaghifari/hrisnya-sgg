<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyMessageTemplate extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['company_id', 'event', 'subject', 'body'];

    public const EVENT_SCREENING_REJECTED = 'screening_rejected';
    public const EVENT_SCREENING_PASSED = 'screening_passed';
    public const EVENT_QUIZ_SENT = 'quiz_sent';
    public const EVENT_QUIZ_PASSED = 'quiz_passed';
    public const EVENT_QUIZ_REJECTED = 'quiz_rejected';
    public const EVENT_INTERVIEW_SCHEDULED = 'interview_scheduled';
    public const EVENT_INTERVIEW_REJECTED = 'interview_rejected';
    public const EVENT_MCU_SCHEDULED = 'mcu_scheduled';
    public const EVENT_MCU_PASSED = 'mcu_passed';
    public const EVENT_MCU_REJECTED = 'mcu_rejected';
    public const EVENT_ONBOARDING_INVITE = 'onboarding_invite';
    public const EVENT_OFFERING_LETTER_READY = 'offering_letter_ready';
    public const EVENT_OFFERING_SIGNED_RECEIVED = 'offering_signed_received';
    public const EVENT_OFFERING_SIGNED_ACCEPTED = 'offering_signed_accepted';
    public const EVENT_OFFERING_REVISION_REQUESTED = 'offering_revision_requested';
    public const EVENT_MANUAL_REJECTED = 'manual_rejected';

    // Email transient (tidak disimpan ke application_notifications — kolom event-nya ENUM +
    // unique per lamaran; event ini bisa berkali-kali). Dipakai hanya untuk cta()/subject.
    public const EVENT_OFFERING_NEGOTIATION_REPLIED = 'offering_negotiation_replied';
    public const EVENT_INTERVIEW_RESCHEDULED = 'interview_rescheduled';
    public const EVENT_MCU_RESCHEDULED = 'mcu_rescheduled';

    public static function forCompanyEvent(int|string $companyId, string $event): ?self
    {
        return static::where('company_id', $companyId)->where('event', $event)->first();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
