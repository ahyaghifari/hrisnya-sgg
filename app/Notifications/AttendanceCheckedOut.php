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
 * Ke karyawan sendiri, saat mesin absensi (access_logs) mencatat scan check-out-nya hari itu.
 * Dikirim dari App\Console\Commands\SyncAccessLogsAttendance, cuma saat entry ditandai
 * `is_new_checkout` (transisi dari belum-ada-checkout ke ada-checkout, bukan tiap batch).
 */
class AttendanceCheckedOut extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected AttendanceRecords $record) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::ATTENDANCE_CHECKED_OUT)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Absensi Pulang Tercatat';
    }

    protected function body(): string
    {
        $time = substr((string) $this->record->check_out_time, 0, 5);
        $date = \Illuminate\Support\Carbon::parse($this->record->date)->translatedFormat('d M Y');

        $summary = "Check-out Anda tercatat pukul {$time} pada {$date}.";

        if ($this->record->working_minutes !== null) {
            $hours = intdiv((int) $this->record->working_minutes, 60);
            $minutes = (int) $this->record->working_minutes % 60;
            $summary .= " Total jam kerja: {$hours} jam {$minutes} menit.";
        }

        return $summary;
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
