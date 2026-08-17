<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\Counseling\Counseling;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Ke konselor yang ditunjuk, saat karyawan submit Konsul baru — isi keluhan TIDAK disertakan
 * terlepas is_secret (lihat Counseling::isContentVisibleTo()), notifikasi cuma bilang ada yang
 * perlu ditanggapi.
 */
class CounselingReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Counseling $counseling) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::COUNSELING_RECEIVED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Konsul Baru';
    }

    protected function body(): string
    {
        return 'Ada karyawan yang menunjuk Anda sebagai konselor — cek "Konsul ke Saya" untuk menanggapi.';
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'counseling_id' => $this->counseling->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['counseling_id' => $this->counseling->id])
            ->action('Lihat', 'view');
    }
}
