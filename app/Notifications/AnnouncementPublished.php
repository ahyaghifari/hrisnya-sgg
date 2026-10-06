<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** Pengumuman dari admin - sengaja tidak mengikuti preferensi notifikasi per jenis (wajib sampai). */
class AnnouncementPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Announcement $announcement) {}

    private function byline(): string
    {
        $name = $this->announcement->publisher?->name;

        return $name ? "Oleh {$name}: " : '';
    }

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->announcement->title,
            'body' => $this->byline() . Str::limit(strip_tags($this->announcement->body), 200),
            'announced_by' => $this->announcement->publisher?->name,
            'announcement_id' => $this->announcement->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->announcement->title)
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->byline() . Str::limit(strip_tags($this->announcement->body), 120))
            ->data(['announcement_id' => $this->announcement->id]);
    }
}
