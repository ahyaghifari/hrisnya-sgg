<?php

namespace App\Notifications\Sikartap;

use App\Enum\NotificationTypeEnum;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** Ke karyawan/pelamar, saat HRD memutuskan status MCU final (fit/unfit) -
 * rewrite queue_hasil_mcu_email() legacy. */
class SikartapHasilMcu extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $sikartapId,
        protected string $statusMcu,
    ) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::SIKARTAP_HASIL_MCU)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Hasil MCU Keluar';
    }

    protected function body(): string
    {
        return $this->statusMcu === 'fit'
            ? 'Selamat, Anda dinyatakan FIT dari hasil MCU.'
            : 'Anda dinyatakan UNFIT dari hasil MCU.';
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
