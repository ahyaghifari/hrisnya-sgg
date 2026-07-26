<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\Attendance\AttendanceRecords;
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
 */
class AttendanceCheckedIn extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected AttendanceRecords $record) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::ATTENDANCE_CHECKED_IN)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Absensi Masuk Tercatat';
    }

    protected function body(): string
    {
        $time = substr((string) $this->record->check_in_time, 0, 5);
        $date = \Illuminate\Support\Carbon::parse($this->record->date)->translatedFormat('d M Y');

        return "Check-in Anda tercatat pukul {$time} pada {$date}.";
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
