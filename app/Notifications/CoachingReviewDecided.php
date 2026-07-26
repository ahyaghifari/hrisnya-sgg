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

/** Ke karyawan yang dibina (user_id), saat coach approve/reject bukti pelaksanaannya — lihat
 * CoachingService::approve()/reject(). */
class CoachingReviewDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Coaching $coaching, protected bool $approved) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::COACHING_REVIEW_DECIDED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return $this->approved ? 'Coaching Selesai' : 'Bukti Coaching Ditolak';
    }

    protected function body(): string
    {
        return $this->approved
            ? "Coaching \"{$this->coaching->title}\" ditandai selesai oleh coach."
            : "Bukti untuk coaching \"{$this->coaching->title}\" ditolak coach — upload bukti baru.";
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
