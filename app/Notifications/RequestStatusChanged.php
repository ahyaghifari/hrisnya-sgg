<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class RequestStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $moduleType,
        protected string $moduleId,
        protected string $status,
        protected string $actorName,
    ) {}

    public function via(object $notifiable): array
    {
        $type = match ($this->status) {
            'approved' => NotificationTypeEnum::APPROVAL_APPROVED,
            'rejected' => NotificationTypeEnum::APPROVAL_REJECTED,
            'cancelled' => NotificationTypeEnum::APPROVAL_CANCELLED,
            default => null,
        };

        if ($type && ! app(NotificationPreferenceService::class)->isEnabled($notifiable, $type)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return match ($this->status) {
            'approved' => 'Pengajuan Disetujui',
            'rejected' => 'Pengajuan Ditolak',
            'cancelled' => 'Pengajuan Dibatalkan',
            default => 'Status Pengajuan Berubah',
        };
    }

    protected function body(): string
    {
        $label = \App\Enum\Approval\ApprovalModuleEnum::tryFrom($this->moduleType)?->notificationLabel() ?? 'Pengajuan';

        return match ($this->status) {
            'approved' => "{$label} Anda disetujui oleh {$this->actorName}",
            'rejected' => "{$label} Anda ditolak oleh {$this->actorName}",
            'cancelled' => "{$label} Anda dibatalkan oleh {$this->actorName}",
            default => "{$label} Anda diperbarui oleh {$this->actorName}",
        };
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'request_type' => $this->moduleType,
            'request_id' => $this->moduleId,
            'status' => $this->status,
            'actor_name' => $this->actorName,
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
