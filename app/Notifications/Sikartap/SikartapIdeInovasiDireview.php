<?php

namespace App\Notifications\Sikartap;

use App\Enum\NotificationTypeEnum;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** Ke karyawan/pelamar, saat ide inovasinya dinilai di salah satu tingkat (atasan/direktur/
 * komisaris) - rewrite queue_ide_inovasi_email() legacy. $tier cuma buat body pesan, bukan
 * jenis notifikasi terpisah (3 tingkat berbagi 1 NotificationTypeEnum, sama seperti legacy
 * yang juga 1 fungsi buat semua tingkat). */
class SikartapIdeInovasiDireview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $sikartapId,
        protected string $tier,
        protected string $status,
        protected ?string $catatan = null,
    ) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::SIKARTAP_IDE_INOVASI_DIREVIEW)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Ide Inovasi Dinilai';
    }

    protected function tierLabel(): string
    {
        return match ($this->tier) {
            'atasan' => 'atasan',
            'direktur' => 'Direktur',
            'komisaris' => 'Komisaris',
            default => $this->tier,
        };
    }

    protected function body(): string
    {
        $hasil = $this->status === 'approved' ? 'disetujui' : 'ditolak';
        $body = "Ide inovasi Anda {$hasil} oleh {$this->tierLabel()}.";

        return $this->catatan ? "{$body} Catatan: {$this->catatan}" : $body;
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
