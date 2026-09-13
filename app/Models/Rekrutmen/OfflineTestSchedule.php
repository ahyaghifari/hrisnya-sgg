<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jadwal 1 sesi tes offline (1 job_opening + 1 offline_test), DB recruitment. Pola sama
 * seperti InterviewSchedule: saveDraft() -> apply() (email undangan auto-kirim), reschedule
 * per sesi / per kandidat. Beda dari InterviewSchedule: apply() TIDAK mengubah status
 * job_applications.status (tes offline tidak menggerakkan status/gating, cuma dicatat).
 */
class OfflineTestSchedule extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['job_opening_id', 'offline_test_id', 'test_date', 'test_time', 'location', 'notes', 'status'];

    protected $casts = ['test_date' => 'date'];

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function offlineTest(): BelongsTo
    {
        return $this->belongsTo(OfflineTest::class);
    }

    public function invitees(): HasMany
    {
        return $this->hasMany(OfflineTestInvitee::class);
    }

    public static function draftFor(string $jobOpeningId, string $offlineTestId): ?self
    {
        return static::where('job_opening_id', $jobOpeningId)->where('offline_test_id', $offlineTestId)
            ->where('status', 'draft')->latest()->first();
    }

    public static function publishedFor(string $jobOpeningId, string $offlineTestId): ?self
    {
        return static::where('job_opening_id', $jobOpeningId)->where('offline_test_id', $offlineTestId)
            ->where('status', 'published')->latest()->first();
    }

    public static function saveDraft(string $jobOpeningId, string $offlineTestId, array $data, array $invitedIds): self
    {
        static::where('job_opening_id', $jobOpeningId)->where('offline_test_id', $offlineTestId)
            ->where('status', 'draft')->delete();

        $schedule = static::create([
            'job_opening_id' => $jobOpeningId,
            'offline_test_id' => $offlineTestId,
            'test_date' => $data['test_date'],
            'test_time' => $data['test_time'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'draft',
        ]);

        foreach ($invitedIds as $appId) {
            OfflineTestInvitee::create([
                'offline_test_schedule_id' => $schedule->id,
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
            $this->notifyInvitee($inv, CompanyMessageTemplate::EVENT_OFFLINE_TEST_SCHEDULED);
        }

        $this->update(['status' => 'published']);

        return [$invitees->count()];
    }

    private function notifyInvitee(OfflineTestInvitee $inv, string $event): void
    {
        ApplicationNotification::createAndSend($inv->jobApplication, $event, [
            'interview_date' => $this->test_date?->translatedFormat('d F Y'),
            'interview_time' => $this->test_time ? substr($this->test_time, 0, 5) : null,
            'location' => $this->location,
            'notes' => $this->notes,
        ]);
    }

    /** Ubah jadwal ini & kirim email perubahan ke semua pesertanya. @return int jumlah dinotifikasi. */
    public function reschedule(array $data): int
    {
        $this->update([
            'test_date' => $data['test_date'],
            'test_time' => $data['test_time'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $invitees = $this->invitees()->with('jobApplication')->get();
        foreach ($invitees as $inv) {
            ApplicationNotification::notifyReschedule($inv->jobApplication, 'offline_test', [
                'date' => $this->test_date?->translatedFormat('d F Y'),
                'time' => $this->test_time ? substr($this->test_time, 0, 5) : null,
                'location' => $this->location,
                'notes' => $this->notes,
            ]);
        }

        return $invitees->count();
    }

    public static function rescheduleFor(string $jobOpeningId, string $offlineTestId, array $data): int
    {
        return static::where('job_opening_id', $jobOpeningId)->where('offline_test_id', $offlineTestId)
            ->where('status', 'published')->get()->sum(fn (self $s) => $s->reschedule($data));
    }

    /** Reschedule 1 orang: dipindah ke jadwal terbit baru (sesi sendiri), peserta lain tak terpengaruh. */
    public static function rescheduleOne(OfflineTestInvitee $invitee, array $data): void
    {
        $new = static::create([
            'job_opening_id' => $invitee->schedule?->job_opening_id,
            'offline_test_id' => $invitee->schedule?->offline_test_id,
            'test_date' => $data['test_date'],
            'test_time' => $data['test_time'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'published',
        ]);

        $invitee->update(['offline_test_schedule_id' => $new->id]);

        ApplicationNotification::notifyReschedule($invitee->jobApplication, 'offline_test', [
            'date' => $new->test_date?->translatedFormat('d F Y'),
            'time' => $new->test_time ? substr($new->test_time, 0, 5) : null,
            'location' => $new->location,
            'notes' => $new->notes,
        ]);
    }
}
