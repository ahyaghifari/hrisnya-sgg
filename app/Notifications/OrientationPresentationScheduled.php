<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\OrientationSubmission;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Dikirim ke karyawan orientasi saat HR menetapkan jadwal presentasi kegiatan orientasinya
 * (tanggal + jam final, boleh beda dari usulan karyawan).
 */
class OrientationPresentationScheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected OrientationSubmission $submission) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::ORIENTATION_PRESENTATION_SCHEDULED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function body(): string
    {
        $at = $this->submission->presentation_confirmed_at?->translatedFormat('l, d F Y H:i');
        $note = $this->submission->presentation_note ? " Catatan: {$this->submission->presentation_note}" : '';

        return "Presentasi kegiatan orientasi Anda dijadwalkan: {$at}.{$note}";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Jadwal Presentasi Orientasi Ditetapkan',
            'body' => $this->body(),
            'orientation_submission_id' => $this->submission->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Jadwal Presentasi Orientasi Ditetapkan')
            ->body($this->body())
            ->data(['orientation_submission_id' => $this->submission->id])
            ->action('Lihat', 'view');
    }
}
