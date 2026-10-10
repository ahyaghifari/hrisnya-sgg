<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Shift\ExtraShiftAttendanceRecord;
use App\Services\Attendance\AttendanceMessageComposer;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Ke karyawan sendiri, saat mesin absensi (access_logs) mencatat scan check-in pertamanya hari
 * itu. Dikirim dari App\Console\Commands\SyncAccessLogsAttendance, cuma saat entry ditandai
 * `is_new_checkin` (bukan tiap batch incremental yang cuma numpang lewat).
 *
 * Isi pesan menyesuaikan keadaan (tepat waktu, terlambat, lebih awal, fleksibel, shift extra) -
 * lihat AttendanceMessageComposer. $context kosong (mis. dari Rekap) = pesan netral dari record.
 */
class AttendanceCheckedIn extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $context */
    public function __construct(
        protected AttendanceRecords|ExtraShiftAttendanceRecord $record,
        protected array $context = [],
    ) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::ATTENDANCE_CHECKED_IN)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    /** @return array{0: string, 1: string} */
    protected function message(): array
    {
        $context = $this->context + [
            'kind' => $this->record instanceof AttendanceRecords ? 'fixed' : 'extra',
            'date' => $this->record->date ?? now()->toDateString(),
            'late_minutes' => (int) ($this->record->late_minutes ?? 0),
        ];

        return AttendanceMessageComposer::checkIn((string) $this->record->check_in_time, $context);
    }

    protected function title(): string
    {
        return $this->message()[0];
    }

    protected function body(): string
    {
        return $this->message()[1];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'attendance_record_id' => $this->record->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['attendance_record_id' => $this->record->id]);
    }
}
