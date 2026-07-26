<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Ke karyawan sendiri, saat scan absensinya (access_logs) TIDAK BISA diproses jadi
 * attendance_records — mis. jadwal tidak ditemukan untuk tanggal itu (lihat
 * AccessLogScheduleReader::groupAndBucket() -> unresolved_schedule, dikonsumsi
 * App\Console\Commands\SyncAccessLogsAttendance). $reason dikirim manusiawi, bukan kode error.
 */
class AttendanceScanRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $date, protected string $reason) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::ATTENDANCE_SCAN_REJECTED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Absensi Tidak Diproses';
    }

    protected function body(): string
    {
        $date = \Illuminate\Support\Carbon::parse($this->date)->translatedFormat('d M Y');

        return "Scan absensi Anda pada {$date} belum bisa diproses: {$this->reason}";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'date' => $this->date,
            'reason' => $this->reason,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['date' => $this->date]);
    }
}
