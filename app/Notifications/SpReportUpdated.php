<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** Satu notifikasi generik untuk tiap langkah alur Berita Acara (instruksi user 2026-10-01) -
 *  judul/isi/tujuan klik ditentukan pemanggil (SpReportService). $documentSignId diisi kalau
 *  tujuannya halaman tanda tangan, kalau tidak $url yang dipakai. */
class SpReportUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $title,
        protected string $body,
        protected ?string $url = null,
        protected ?string $documentSignId = null,
    ) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::SP_REPORT_UPDATE)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'sp_report_url' => $this->documentSignId ? null : $this->url,
            'document_sign_id' => $this->documentSignId,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body)
            ->data(['document_sign_id' => $this->documentSignId, 'sp_report_url' => $this->url])
            ->action('Buka', 'view');
    }
}
