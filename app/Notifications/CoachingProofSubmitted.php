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

/** Ke coach (coach_by), saat karyawan yang dibina mengajukan bukti pelaksanaan (status jadi SUBMITTED). */
class CoachingProofSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Coaching $coaching) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::COACHING_PROOF_SUBMITTED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Bukti Coaching Menunggu Persetujuan';
    }

    protected function body(): string
    {
        return "{$this->coaching->user?->name} mengajukan bukti pelaksanaan untuk: {$this->coaching->title}";
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
