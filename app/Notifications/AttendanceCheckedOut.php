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
 * Ke karyawan sendiri, saat mesin absensi (access_logs) mencatat scan check-out-nya hari itu.
 * Dikirim dari App\Console\Commands\SyncAccessLogsAttendance, cuma saat entry ditandai
 * `is_new_checkout` (transisi dari belum-ada-checkout ke ada-checkout, bukan tiap batch).
 *
 * Isi pesan menyesuaikan keadaan (normal, pulang lebih awal dengan/tanpa izin, fleksibel, shift
 * extra) - lihat AttendanceMessageComposer. $context kosong = pesan netral dari record.
 */
class AttendanceCheckedOut extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $context */
    public function __construct(
        protected AttendanceRecords|ExtraShiftAttendanceRecord $record,
        protected array $context = [],
    ) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::ATTENDANCE_CHECKED_OUT)) {
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
            'early_leave_minutes' => (int) ($this->record->early_leave_minutes ?? 0),
        ];

        return AttendanceMessageComposer::checkOut(
            (string) $this->record->check_out_time,
            $this->record->working_minutes !== null ? (int) $this->record->working_minutes : null,
            $context,
        );
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
