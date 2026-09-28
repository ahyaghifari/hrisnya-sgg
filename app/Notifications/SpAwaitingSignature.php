<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\Sp\EmployeeSp;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** Ke knows_by (bukan pemilik SP), begitu SP terbit dan created_by sudah menandatangani -
 *  TTE Fase 1 (instruksi user 2026-09-28): tanda tangan knows_by baru tercatat setelah dia
 *  sendiri buka SignDocumentsPage (routes/documents.php) dan konfirmasi password, bukan
 *  otomatis. */
class SpAwaitingSignature extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected EmployeeSp $employeeSp) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::SP_AWAITING_SIGNATURE)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Menunggu Tanda Tangan Anda';
    }

    protected function body(): string
    {
        return "Anda ditunjuk \"Mengetahui\" pada {$this->employeeSp->sp_level->getLabel()} atas nama {$this->employeeSp->user?->name} - perlu ditandatangani.";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            // ID `documents` (registry generik), BUKAN ID employee_sps - SignDocumentsPage
            // route-nya generik lintas jenis dokumen, lihat routes/documents.php.
            'document_sign_id' => $this->employeeSp->document?->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['document_sign_id' => $this->employeeSp->document?->id])
            ->action('Tandatangani', 'view');
    }
}
