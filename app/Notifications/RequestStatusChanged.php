<?php

namespace App\Notifications;

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
        $label = match ($this->moduleType) {
            'task_request' => 'Pengajuan tugas',
            'swap_shift_request' => 'Pengajuan tukar shift',
            default => 'Pengajuan absensi',
        };

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
            ->icon('/pwa/icons/icon-192.png')
            ->body($this->body())
            ->data(['request_type' => $this->moduleType, 'request_id' => $this->moduleId])
            ->action('Lihat', 'view');
    }
}
