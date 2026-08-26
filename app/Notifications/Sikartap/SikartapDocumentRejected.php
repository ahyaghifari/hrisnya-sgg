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
 * Ke karyawan/pelamar, saat HRD menolak 1 dokumen administratif pengajuan karyawan tetap
 * (rewrite queue_dokumen_individual_email() legacy) - SENGAJA cuma reject, bukan approve juga
 * (instruksi user 2026-08-26: approve per dokumen tidak actionable, masih nunggu dokumen lain).
 * Pola sama persis ApprovalNeedsAction/RequestStatusChanged.
 */
class SikartapDocumentRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $sikartapId,
        protected string $docLabel,
        protected string $catatan,
    ) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::SIKARTAP_DOCUMENT_REJECTED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Dokumen Ditolak';
    }

    protected function body(): string
    {
        return "{$this->docLabel} pada pengajuan karyawan tetap Anda ditolak: {$this->catatan}";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'sikartap_id' => $this->sikartapId,
            'doc_label' => $this->docLabel,
            'catatan' => $this->catatan,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['sikartap_id' => $this->sikartapId])
            ->action('Lihat', 'view');
    }
}
