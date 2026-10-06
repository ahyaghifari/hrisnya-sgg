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

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return '📢 ' . $this->announcement->title;
    }

    /** Deskripsi singkat; pengumuman lama tanpa deskripsi memakai cuplikan isi. */
    protected function body(): string
    {
        return $this->announcement->summary ?: Str::limit(trim(strip_tags($this->announcement->body)), 200);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'announcement_id' => $this->announcement->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['announcement_id' => $this->announcement->id]);
    }
}
