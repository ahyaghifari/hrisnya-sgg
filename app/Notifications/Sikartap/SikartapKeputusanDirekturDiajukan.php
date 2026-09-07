<?php

namespace App\Notifications\Sikartap;

use App\Enum\NotificationTypeEnum;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Ke Direktur (pemegang jabatan `sikartap_direktur_job_position_id`) saat HRD mengajukan
 * pengajuan karyawan tetap untuk diputuskan. Menggantikan notifikasi generik Approval Engine
 * yang dulu dikirim ke approver — Sikartap Keputusan Direktur sekarang lepas Approval Engine.
 */
class SikartapKeputusanDirekturDiajukan extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $sikartapId,
        protected string $applicantName,
    ) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::SIKARTAP_KEPUTUSAN_DIREKTUR)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Keputusan Direktur Menunggu';
    }

    protected function body(): string
    {
        return "Pengajuan karyawan tetap a.n. {$this->applicantName} menunggu keputusan Anda.";
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
