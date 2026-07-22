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
 * Ke approver PERTAMA saat pengajuan Lembur baru disubmit. Nama class ini SENGAJA sama persis
 * dengan yang sebelumnya cuma string mentah di NotificationService (dulu insert manual ke
 * tabel notifications, bukan lewat class asli - jadi tidak pernah lewat WebPush). Dijadikan
 * class Notification asli supaya dapat WebPush juga (sama seperti RequestStatusChanged/
 * ApprovalNeedsAction), TANPA mengubah nilai kolom `type` yang sudah dipakai
 * App\Filament\Widgets\NotificationWidget::getTableQuery() - jadi widget admin tetap jalan
 * tanpa perlu diubah.
 */
class OvertimeRequestSubmitted extends Notification implements ShouldQueue
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
        return 'Pengajuan Lembur Baru';
    }

    protected function body(): string
    {
        return "Pengajuan lembur dari {$this->requesterName} memerlukan persetujuan Anda";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'request_type' => 'overtime',
            'request_id' => $this->requestData['request_id'],
            'requester_name' => $this->requesterName,
            'overtime_type' => $this->requestData['overtime_type'],
            'date' => $this->requestData['date'],
            'start_time' => $this->requestData['start_time'],
            'end_time' => $this->requestData['end_time'],
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->icon('/pwa/icons/icon-192-v3.png')
            ->body($this->body())
            ->data(['request_type' => 'overtime', 'request_id' => $this->requestData['request_id']])
            ->action('Lihat', 'view');
    }
}
