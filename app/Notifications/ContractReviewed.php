<?php

namespace App\Notifications;

use App\Enum\ContractDecisionEnum;
use App\Enum\NotificationTypeEnum;
use App\Models\ContractSubmission;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Dikirim ke karyawan saat pengajuan perpanjangan kontraknya diputuskan penilai.
 */
class ContractReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ContractSubmission $submission) {}

    public function via(object $notifiable): array
    {
        if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::CONTRACT_REVIEWED)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    protected function decisionLabel(): string
    {
        return $this->submission->decision
            ? ContractDecisionEnum::from($this->submission->decision)->getLabel()
            : 'Selesai dinilai';
    }

    protected function body(): string
    {
        $note = $this->submission->decision_note ? " Catatan: {$this->submission->decision_note}" : '';

        return "Hasil pengajuan perpanjangan kontrak Anda: {$this->decisionLabel()}.{$note}";
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Perpanjangan Kontrak Diputuskan',
            'body' => $this->body(),
            'contract_submission_id' => $this->submission->id,
            'decision' => $this->submission->decision,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Perpanjangan Kontrak Diputuskan')
            ->body($this->body())
            ->data(['contract_submission_id' => $this->submission->id])
            ->action('Lihat', 'view');
    }
}
