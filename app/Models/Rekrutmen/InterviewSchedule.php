<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jadwal wawancara 1 lowongan (DB recruitment). Port dari project `rekrutmen`. Pola:
 * saveDraft() -> apply(). apply() dari HRIS bikin draft notif (bukan kirim email) — kirim
 * di Fase 8.
 */
class InterviewSchedule extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['job_opening_id', 'interview_date', 'interview_time', 'location', 'notes', 'status'];

    protected $casts = ['interview_date' => 'date'];

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function invitees(): HasMany
    {
        return $this->hasMany(InterviewInvitee::class);
    }

    public static function draftForOpening(string $jobOpeningId): ?self
    {
        return static::where('job_opening_id', $jobOpeningId)->where('status', 'draft')->latest()->first();
    }

    public static function publishedForOpening(string $jobOpeningId): ?self
    {
        return static::where('job_opening_id', $jobOpeningId)->where('status', 'published')->latest()->first();
    }

    /** Notif email perubahan jadwal ke 1 pelamar kalau masih di tahap wawancara. */
    private function notifyInvitee(InterviewInvitee $inv): bool
    {
        if ($inv->jobApplication?->status !== JobApplication::STATUS_INTERVIEW) {
            return false;
        }

        ApplicationNotification::notifyReschedule($inv->jobApplication, 'interview', [
            'date' => $this->interview_date?->translatedFormat('d F Y'),
            'time' => $this->interview_time ? substr($this->interview_time, 0, 5) : null,
            'location' => $this->location,
            'notes' => $this->notes,
        ]);

        return true;
    }

    /**
     * Ubah jadwal ini & kirim email perubahan ke semua pesertanya yang masih di tahap
     * wawancara. @return int jumlah pelamar dinotifikasi.
     */
    public function reschedule(array $data): int
    {
        $this->update([
            'interview_date' => $data['interview_date'],
            'interview_time' => $data['interview_time'] ?? null,
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
     * mengganggu peserta lain. Email perubahan dikirim ke pelamar itu.
     */
    public static function rescheduleOne(InterviewInvitee $invitee, array $data): void
    {
        $new = static::create([
            'job_opening_id' => $invitee->schedule?->job_opening_id ?? $invitee->jobApplication?->job_opening_id,
            'interview_date' => $data['interview_date'],
            'interview_time' => $data['interview_time'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'published',
        ]);

        $invitee->update(['interview_schedule_id' => $new->id]);
        $new->notifyInvitee($invitee->fresh('jobApplication'));
    }

    public static function saveDraft(string $jobOpeningId, array $data, array $invitedIds): self
    {
        static::where('job_opening_id', $jobOpeningId)->where('status', 'draft')->delete();

        $schedule = static::create([
            'job_opening_id' => $jobOpeningId,
            'interview_date' => $data['interview_date'],
            'interview_time' => $data['interview_time'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'draft',
        ]);

        foreach ($invitedIds as $appId) {
            InterviewInvitee::create([
                'interview_schedule_id' => $schedule->id,
                'job_application_id' => $appId,
            ]);
        }

        return $schedule;
    }

    /** @return array{0:int} [jumlah diundang] */
    public function apply(): array
    {
        $invitees = $this->invitees()->with('jobApplication')->get();

        foreach ($invitees as $inv) {
            $inv->jobApplication->update(['status' => JobApplication::STATUS_INTERVIEW]);
            ApplicationNotification::createAndSend($inv->jobApplication, CompanyMessageTemplate::EVENT_INTERVIEW_SCHEDULED, [
                'interview_date' => $this->interview_date?->translatedFormat('d F Y'),
                'interview_time' => $this->interview_time ? substr($this->interview_time, 0, 5) : null,
                'location' => $this->location,
                'notes' => $this->notes,
            ]);
        }

        $this->update(['status' => 'published']);

        return [$invitees->count()];
    }
}
