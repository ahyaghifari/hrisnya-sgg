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
 * Ke approver PERTAMA saat pengajuan Cuti baru disubmit. Lihat catatan lengkap di
 * OvertimeRequestSubmitted (pola sama persis, cuma beda konten) - nama class ini sengaja sama
 * dengan yang dirujuk App\Filament\Widgets\NotificationWidget.
 */
class LeaveRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected array $requestData, protected string $requesterName) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::APPROVAL_NEEDS_ACTION)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function title(): string
    {
        return 'Pengajuan Cuti Baru';
    }

    protected function body(): string
    {
        return "Pengajuan cuti dari {$this->requesterName} memerlukan persetujuan Anda";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'request_type' => 'leave',
            'request_id' => $this->requestData['request_id'],
            'requester_name' => $this->requesterName,
            'leave_type' => $this->requestData['leave_type'],
            'start_date' => $this->requestData['start_date'],
            'end_date' => $this->requestData['end_date'],
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['request_type' => 'leave', 'request_id' => $this->requestData['request_id']])
            ->action('Lihat', 'view');
    }
}
