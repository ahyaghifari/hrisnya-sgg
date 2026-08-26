<?php

namespace App\Notifications\Sikartap;

use App\Enum\NotificationTypeEnum;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** Ke karyawan/pelamar, saat HRD menetapkan jadwal wawancara -
 * rewrite queue_jadwal_wawancara_email() legacy. */
class SikartapJadwalWawancaraDitetapkan extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $sikartapId,
        protected string $tanggal,
        protected string $tempat,
    ) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::SIKARTAP_JADWAL_WAWANCARA)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Jadwal Wawancara Ditetapkan';
    }

    protected function body(): string
    {
        return "Wawancara Anda dijadwalkan {$this->tanggal} di {$this->tempat}.";
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
