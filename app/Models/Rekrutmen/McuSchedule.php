<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jadwal MCU 1 lowongan (DB recruitment). Port dari project `rekrutmen`. apply() dari HRIS
 * -> status `mcu` + draft notif EVENT_MCU_SCHEDULED (kirim email di Fase 8).
 */
class McuSchedule extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['job_opening_id', 'mcu_date', 'mcu_time', 'location', 'notes', 'status'];

    protected $casts = ['mcu_date' => 'date'];

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function invitees(): HasMany
    {
        return $this->hasMany(McuInvitee::class);
    }

    public static function draftForOpening(string $jobOpeningId): ?self
    {
        return static::where('job_opening_id', $jobOpeningId)->where('status', 'draft')->latest()->first();
    }

    public static function saveDraft(string $jobOpeningId, array $data, array $invitedIds): self
    {
        static::where('job_opening_id', $jobOpeningId)->where('status', 'draft')->delete();

        $schedule = static::create([
            'job_opening_id' => $jobOpeningId,
            'mcu_date' => $data['mcu_date'],
            'mcu_time' => $data['mcu_time'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'draft',
        ]);

        foreach ($invitedIds as $appId) {
            McuInvitee::create(['mcu_schedule_id' => $schedule->id, 'job_application_id' => $appId]);
        }

        return $schedule;
    }

    /** @return array{0:int} */
    public function apply(): array
    {
        $invitees = $this->invitees()->with('jobApplication')->get();

        foreach ($invitees as $inv) {
            $inv->jobApplication->update(['status' => JobApplication::STATUS_MCU]);
            ApplicationNotification::createDraftFor($inv->jobApplication, CompanyMessageTemplate::EVENT_MCU_SCHEDULED, [
                'interview_date' => $this->mcu_date?->translatedFormat('d F Y'),
                'interview_time' => $this->mcu_time ? substr($this->mcu_time, 0, 5) : null,
                'location' => $this->location,
                'notes' => $this->notes,
            ]);
        }

        $this->update(['status' => 'published']);

        return [$invitees->count()];
    }
}
