<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jadwal MCU 1 lowongan (DB recruitment). Port dari project `rekrutmen`. apply() dari HRIS
 * -> status `mcu` + email undangan EVENT_MCU_SCHEDULED (auto-kirim, createAndSend).
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

    public static function publishedForOpening(string $jobOpeningId): ?self
    {
        return static::where('job_opening_id', $jobOpeningId)->where('status', 'published')->latest()->first();
    }

    /** Notif email perubahan jadwal ke 1 kandidat kalau masih di tahap MCU. */
    private function notifyInvitee(McuInvitee $inv): bool
    {
        if ($inv->jobApplication?->status !== JobApplication::STATUS_MCU) {
            return false;
        }

        ApplicationNotification::notifyReschedule($inv->jobApplication, 'mcu', [
            'date' => $this->mcu_date?->translatedFormat('d F Y'),
            'time' => $this->mcu_time ? substr($this->mcu_time, 0, 5) : null,
            'location' => $this->location,
            'notes' => $this->notes,
        ]);

        return true;
    }

    /**
     * Ubah jadwal ini & kirim email perubahan ke semua pesertanya yang masih di tahap MCU.
     * @return int jumlah kandidat dinotifikasi.
     */
    public function reschedule(array $data): int
    {
        $this->update([
            'mcu_date' => $data['mcu_date'],
            'mcu_time' => $data['mcu_time'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $notified = 0;
        foreach ($this->invitees()->with('jobApplication')->get() as $inv) {
            $notified += $this->notifyInvitee($inv) ? 1 : 0;
        }

        return $notified;
    }

    /** Reschedule massal: semua jadwal terbit lowongan ini dipindah ke tanggal baru. */
    public static function rescheduleOpening(string $jobOpeningId, array $data): int
    {
        return static::where('job_opening_id', $jobOpeningId)->where('status', 'published')->get()
            ->sum(fn (self $s) => $s->reschedule($data));
    }

    /**
     * Reschedule 1 orang: peserta dipindah ke jadwal terbit baru (tanggal sendiri) tanpa
     * mengganggu peserta lain. Email perubahan dikirim ke kandidat itu.
     */
    public static function rescheduleOne(McuInvitee $invitee, array $data): void
    {
        $new = static::create([
            'job_opening_id' => $invitee->schedule?->job_opening_id ?? $invitee->jobApplication?->job_opening_id,
            'mcu_date' => $data['mcu_date'],
            'mcu_time' => $data['mcu_time'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'published',
        ]);

        $invitee->update(['mcu_schedule_id' => $new->id]);
        $new->notifyInvitee($invitee->fresh('jobApplication'));
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
            ApplicationNotification::createAndSend($inv->jobApplication, CompanyMessageTemplate::EVENT_MCU_SCHEDULED, [
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
