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

/** Ke karyawan (user_id), saat HR menyetujui rekomendasi dan SP resmi terbit (§2.4). */
class SpIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected EmployeeSp $employeeSp) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::SP_ISSUED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Anda Mendapatkan Surat Peringatan!';
    }

    protected function body(): string
    {
        return "😱 Anda menerima {$this->employeeSp->sp_level->getLabel()}";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'employee_sp_id' => $this->employeeSp->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['employee_sp_id' => $this->employeeSp->id])
            ->action('Lihat', 'view');
    }
}
