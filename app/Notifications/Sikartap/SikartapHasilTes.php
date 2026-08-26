<?php

namespace App\Notifications\Sikartap;

use App\Enum\NotificationTypeEnum;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** Ke karyawan/pelamar, saat hasil tes bidang/psikotes diinput HRD -
 * rewrite queue_hasil_tes_email() legacy. */
class SikartapHasilTes extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $sikartapId,
        protected string $statusTes,
    ) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::SIKARTAP_HASIL_TES)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Hasil Tes Keluar';
    }

    protected function body(): string
    {
        return $this->statusTes === 'lulus'
            ? 'Selamat, Anda dinyatakan lulus tes bidang/psikotes, lanjut ke tahap wawancara.'
            : 'Anda dinyatakan tidak lulus tes bidang/psikotes.';
    }

    public function toDatabase(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->body(), 'sikartap_id' => $this->sikartapId, 'created_at' => now()->toISOString()];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)->title($this->title())->icon('/pwa/icons/icon-192-v3.png')->body($this->body())
            ->data(['sikartap_id' => $this->sikartapId])->action('Lihat', 'view');
    }
}
