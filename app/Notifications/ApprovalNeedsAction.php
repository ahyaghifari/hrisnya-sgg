<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Ke approver yang SEDANG jadi giliran (bukan cuma approver pertama) - dikirim ulang tiap kali
 * ApprovalFlowService::advanceStep() naik ke step berikutnya, supaya approver step 2/3/dst juga
 * dikabari, bukan cuma approver pertama seperti sebelumnya.
 */
class ApprovalNeedsAction extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $moduleType,
        protected string $moduleId,
        protected string $requesterName,
    ) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::APPROVAL_NEEDS_ACTION)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Menunggu Persetujuan Anda';
    }

    protected function body(): string
    {
        $label = \App\Enum\Approval\ApprovalModuleEnum::tryFrom($this->moduleType)?->notificationLabel() ?? 'Pengajuan';

        return "{$label} dari {$this->requesterName} memerlukan persetujuan Anda";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'request_type' => $this->moduleType,
            'request_id' => $this->moduleId,
            'requester_name' => $this->requesterName,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['request_type' => $this->moduleType, 'request_id' => $this->moduleId])
            ->action('Lihat', 'view');
    }
}
