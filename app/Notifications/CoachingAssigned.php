<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\Coaching\Coaching;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** Ke karyawan yang dibina (user_id), saat atasan/HC membuat sesi Coaching baru untuknya. */
class CoachingAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Coaching $coaching) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::COACHING_ASSIGNED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Coaching Baru';
    }

    protected function body(): string
    {
        return "Anda mendapatkan sesi coaching: {$this->coaching->title}";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'coaching_id' => $this->coaching->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['coaching_id' => $this->coaching->id])
            ->action('Lihat', 'view');
    }
}
