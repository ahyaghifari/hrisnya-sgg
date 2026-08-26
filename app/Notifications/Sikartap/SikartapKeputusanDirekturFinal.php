<?php

namespace App\Notifications\Sikartap;

use App\Enum\NotificationTypeEnum;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** Ke karyawan/pelamar (bukan HRD pengaju), saat Direktur memutuskan pengajuan karyawan tetap
 * - rewrite queue_keputusan_final_email() legacy. Beda dari notifikasi generik
 * RequestStatusChanged (yang dikirim ApprovalFlowService::advanceStep() ke created_by snapshot,
 * yaitu HRD yang klik "Ajukan ke Direktur", BUKAN pelamar) - pelamar butuh dikabari langsung
 * soal nasibnya, jadi dipanggil terpisah dari SikartapDirectorDecisionService::approve()/
 * reject(), bukan menggantikan notifikasi generik itu. */
class SikartapKeputusanDirekturFinal extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $sikartapId,
        protected bool $lulus,
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
        return 'Keputusan Direktur';
    }

    protected function body(): string
    {
        return $this->lulus
            ? 'Selamat, Anda dinyatakan LULUS menjadi karyawan tetap oleh Direktur.'
            : 'Anda dinyatakan TIDAK LULUS pengajuan karyawan tetap oleh Direktur.';
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
