<?php

namespace App\Notifications;

use App\Enum\NotificationTypeEnum;
use App\Models\ContractSubmission;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Dikirim ke penilai (resolve dari sikartap_reviewers, context `contract`) saat karyawan
 * mengajukan perpanjangan kontrak.
 */
class ContractReviewRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ContractSubmission $submission) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::CONTRACT_REVIEW_REQUESTED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function body(): string
    {
        $name = $this->submission->user?->name ?? 'Karyawan';

        return "{$name} mengajukan perpanjangan kontrak. Mohon dinilai.";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Pengajuan Perpanjangan Kontrak Perlu Dinilai',
            'body' => $this->body(),
            'contract_submission_id' => $this->submission->id,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Pengajuan Perpanjangan Kontrak Perlu Dinilai')
            ->body($this->body())
            ->data(['contract_submission_id' => $this->submission->id])
            ->action('Lihat', 'view');
    }
}
