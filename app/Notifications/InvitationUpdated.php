<?php

namespace App\Notifications;

use App\Enum\InvitationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notifikasi untuk SEMUA jenis undangan (klarifikasi SP, coaching, dst.): dibuat, diubah jadwalnya, dibatalkan.
 * Selalu terkirim (undangan resmi, tidak mengikuti pengaturan notifikasi penerima). `request_type` = 'invitation'
 * dipakai lonceng & halaman Notifikasi untuk memberi warna ungu khusus (sama dengan widget Undangan di beranda).
 */
class InvitationUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $title,
        protected string $body,
        protected string $url,
        protected InvitationType $type,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'request_type' => 'invitation',
            'invitation_type' => $this->type->value,
            'invitation_label' => $this->type->label(),
            'invitation_url' => $this->url,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body)
            ->data(['request_type' => 'invitation', 'invitation_url' => $this->url])
            ->action('Buka', 'view');
    }
}